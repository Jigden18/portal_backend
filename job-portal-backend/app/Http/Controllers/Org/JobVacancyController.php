<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\JobVacancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobVacancyController extends Controller
{
    // GET my vacancies
    public function index(Request $request)
    {
        $organization = $request->organization;

        $query = JobVacancy::with('organization')
            ->where('organization_id', $organization->id);

        if ($request->filled('status') && in_array($request->status, ['Active', 'Inactive'])) {
            $query->where('status', $request->status);
        }

        $vacancies = $query->latest()->paginate(15);

        return response()->json([
            'vacancies' => $vacancies->map(function ($vacancy) {
                return $this->formatVacancy($vacancy);
            }),
            'pagination' => [
                'total'        => $vacancies->total(),
                'per_page'     => $vacancies->perPage(),
                'current_page' => $vacancies->currentPage(),
                'last_page'    => $vacancies->lastPage(),
            ]
        ]);
    }

    // POST create
    public function store(Request $request)
    {
        $organization = $request->organization;

        $validated = $request->validate([
            'position'     => ['required', 'string', 'max:255'],
            'salary'       => ['nullable', 'numeric'],
            'currency'     => ['required_with:salary', 'string', 'max:3'], // USD, EUR, etc.
            'location'     => ['required', 'string', 'max:255'],
            'type'         => ['required', Rule::in(['Full Time', 'Part Time', 'Internship', 'Contract'])],
            'requirements' => ['nullable', 'array'],
        ]);

        $field = JobVacancy::inferField($validated['position']);

        $vacancy = JobVacancy::create([
            'position'        => $validated['position'],
            'field'           => $field,
            'salary'          => $validated['salary'] ?? null,
            'currency'        => $validated['currency'] ?? 'USD',
            'location'        => $validated['location'],
            'type'            => $validated['type'],
            'requirements'    => $validated['requirements'] ?? [],
            'status'          => 'Active',
            'organization_id' => $organization->id,
        ]);

        return response()->json([
            'vacancy' => $this->formatVacancy($vacancy->load('organization'))
        ], 201);
    }

    // GET one
    public function show($id, Request $request)
    {
        $organization = $request->organization;

        $vacancy = JobVacancy::with('organization')
            ->where('organization_id', $organization->id)
            ->findOrFail($id);

        return response()->json([
            'vacancy' => $this->formatVacancy($vacancy)
        ]);
    }

    // PUT update
    public function update(Request $request, $id)
    {
        $organization = $request->organization;

        $vacancy = JobVacancy::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'position'     => ['sometimes', 'string', 'max:255'],
            'salary'       => ['nullable', 'numeric'],
            'currency'     => ['sometimes', 'string', 'max:3'],
            'location'     => ['sometimes', 'string', 'max:255'],
            'type'         => ['sometimes', Rule::in(['Full Time', 'Part Time', 'Internship', 'Contract'])],
            'requirements' => ['nullable', 'array'],
            'status'       => ['sometimes', Rule::in(['Active', 'Inactive'])],
        ]);

        if (isset($validated['position'])) {
            $validated['field'] = JobVacancy::inferField($validated['position']);
        }

        $vacancy->update($validated);

        return response()->json([
            'vacancy' => $this->formatVacancy($vacancy->load('organization'))
        ]);
    }

    // PATCH toggle status
    public function toggleStatus($id, Request $request)
    {
        $organization = $request->organization;

        $vacancy = JobVacancy::where('organization_id', $organization->id)->findOrFail($id);
        $vacancy->status = $vacancy->status === 'Active' ? 'Inactive' : 'Active';
        $vacancy->save();

        return response()->json([
            'vacancy' => $this->formatVacancy($vacancy->load('organization'))
        ]);
    }

    // DELETE
    public function destroy($id, Request $request)
    {
        $organization = $request->organization;

        $vacancy = JobVacancy::where('organization_id', $organization->id)->findOrFail($id);
        $vacancy->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /**
     * Format vacancy response with organization details
     */
    private function formatVacancy($vacancy)
    {
        return [
            'id'          => $vacancy->id,
            'position'    => $vacancy->position,
            'field'       => $vacancy->field,
            'salary'      => $vacancy->salary,
            'currency'    => $vacancy->currency,
            'location'    => $vacancy->location,
            'type'        => $vacancy->type,
            'status'      => $vacancy->status,
            'requirements'=> $vacancy->requirements,
            'organization'=> [
                'id'   => $vacancy->organization->id,
                'name' => $vacancy->organization->name,
                'logo' => $vacancy->organization->logo_url ?? null,
            ],
            'created_at'  => $vacancy->created_at,
            'updated_at'  => $vacancy->updated_at,
        ];
    }
}
