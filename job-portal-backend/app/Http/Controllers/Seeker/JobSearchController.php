<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Models\JobVacancy;
use App\Models\Currency;
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

        $query = JobVacancy::with(['organization', 'currency']) // keep full org relation
            ->where('status', 'Active');

        // Filters
        // FIELD
        if ($request->filled('field')) {
            $rawFields = explode(',', $request->field);
            $normalized = collect($rawFields)->map(fn($f) => JobVacancy::inferField($f) ?? $f)->unique();
            $query->whereIn('field', $normalized);
        }
        // TYPE
        if ($request->filled('type')) {
            $types = explode(',', $request->type);
            $query->whereIn('type', $types);
        }
        // LOCATION
        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }
        // SEARCH (position, field)
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('position', 'like', "%$q%")
                    ->orWhere('field', 'like', "%$q%");
            });
        }

        // CURRENCY filter
        if ($request->filled('currency')) {
            $currencies = explode(',', $request->currency);
            $query->whereHas('currency', fn($sub) => $sub->whereIn('code', $currencies));
        }

        if ($request->filled('min_salary')) {
            $query->where('salary', '>=', (float) $request->min_salary);
        }
        if ($request->filled('max_salary')) {
            $query->where('salary', '<=', (float) $request->max_salary);
        }

        $query->orderBy('created_at', 'desc');

        // Fetch all results
        $vacancies = $query->get();

        // fetch all saved job IDs once (optimization)
        $savedIds = $profile 
            ? $profile->bookmarks()->pluck('job_vacancy_id')->toArray()
            : [];

        return response()->json([
            'vacancies' => $vacancies->map(
                fn($vacancy) => $this->formatVacancy($vacancy, $savedIds)),
        ]);
    }

    /**
     * GET /api/jobs/{id}
     * Show a single job
     */
    public function show($id, Request $request)
    {
        $profile = $request->profile;

        $vacancy = JobVacancy::with(['organization', 'currency'])
            ->where('status', 'Active')
            ->findOrFail($id);
        
        // Check if this one job is saved
        $isSaved = $profile 
            ? $profile->bookmarks()->where('job_vacancy_id', $vacancy->id)->exists()
            : false;

        return response()->json([
            'vacancy' => $this->formatVacancy($vacancy, [], $isSaved),
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
            'currencies' => Currency::all(), // Fetch all currencies
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
            'currency' => [
                'code' => $vacancy->currency->code ?? null,
                'symbol' => $vacancy->currency->symbol ?? null,
            ],
            'location'     => $vacancy->location,
            'type'         => $vacancy->type,
            'status'       => $vacancy->status,
            'requirements' => $vacancy->requirements,
            'organization' => [
                'id'   => $vacancy->organization->id,
                'name' => $vacancy->organization->name,
                'logo' => $vacancy->organization->logo_url ?? null,
            ],
            // NEW: is_saved flag
            'is_saved'     => $isSaved !== null
                                ? $isSaved
                                : in_array($vacancy->id, $savedIds),
            'created_at'   => $vacancy->created_at,
            'updated_at'   => $vacancy->updated_at,
        ];
    }
}
