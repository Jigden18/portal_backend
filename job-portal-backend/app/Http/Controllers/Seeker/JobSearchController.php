<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Models\JobVacancy;
use Illuminate\Http\Request;

class JobSearchController extends Controller
{
    /**
     * GET /api/jobs
     * List active jobs with filters
     */
    public function index(Request $request)
    {
        $profile = $request->profile; // available thanks to middleware

        $query = JobVacancy::with('organization') // keep full org relation
            ->where('status', 'Active');

        // Filters
        if ($request->filled('field')) {
            $fields = explode(',', $request->field);
            $query->whereIn('field', $fields);
        }

        if ($request->filled('type')) {
            $types = explode(',', $request->type);
            $query->whereIn('type', $types);
        }

        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('position', 'like', "%$q%")
                    ->orWhere('field', 'like', "%$q%");
            });
        }

        if ($request->filled('min_salary')) {
            $query->where('salary', '>=', (float) $request->min_salary);
        }
        if ($request->filled('max_salary')) {
            $query->where('salary', '<=', (float) $request->max_salary);
        }

        $query->orderBy('created_at', 'desc');

        $vacancies = $query->paginate(15);

        return response()->json([
            'vacancies' => $vacancies->map(fn($vacancy) => $this->formatVacancy($vacancy)),
            'pagination' => [
                'total'        => $vacancies->total(),
                'per_page'     => $vacancies->perPage(),
                'current_page' => $vacancies->currentPage(),
                'last_page'    => $vacancies->lastPage(),
            ]
        ]);
    }

    /**
     * GET /api/jobs/{id}
     * Show a single job
     */
    public function show($id, Request $request)
    {
        $profile = $request->profile;

        $vacancy = JobVacancy::with('organization')
            ->where('status', 'Active')
            ->findOrFail($id);

        return response()->json([
            'vacancy' => $this->formatVacancy($vacancy)
        ]);
    }

    /**
     * GET /api/jobs/filters/options
     * Dynamic filter options for the UI
     */
    public function filterOptions(Request $request)
    {
        $profile = $request->profile; // still here for future customization

        return response()->json([
            'fields'     => JobVacancy::whereNotNull('field')->distinct()->pluck('field'),
            'locations'  => JobVacancy::distinct()->pluck('location'),
            'types'      => JobVacancy::distinct()->pluck('type'),
            'currencies' => JobVacancy::distinct()->pluck('currency'),
        ]);
    }

    /**
     * Standard format for vacancies (same as org side)
     */
    private function formatVacancy($vacancy)
    {
        return [
            'id'           => $vacancy->id,
            'position'     => $vacancy->position,
            'field'        => $vacancy->field,
            'salary'       => $vacancy->salary,
            'currency'     => $vacancy->currency,
            'location'     => $vacancy->location,
            'type'         => $vacancy->type,
            'status'       => $vacancy->status,
            'requirements' => $vacancy->requirements,
            'organization' => [
                'id'   => $vacancy->organization->id,
                'name' => $vacancy->organization->name,
                'logo' => $vacancy->organization->logo_url ?? null,
            ],
            'created_at'   => $vacancy->created_at,
            'updated_at'   => $vacancy->updated_at,
        ];
    }
}
