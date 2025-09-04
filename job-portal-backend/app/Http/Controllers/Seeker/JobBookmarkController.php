<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Models\JobVacancy;
use Illuminate\Http\Request;

class JobBookmarkController extends Controller
{
    /**
     * GET /api/jobs/saved
     * List all saved jobs for the seeker
     */
    public function index(Request $request)
    {
        $profile = $request->profile;

        $savedJobs = $profile->bookmarks()
            ->with(['organization', 'currency'])
            ->latest()
            ->get();

        return response()->json([
            'vacancies' => $savedJobs->map(fn($vacancy) => $this->formatVacancy($vacancy))
        ]);
    }

    /**
     * POST /api/jobs/{id}/save
     * Save/bookmark a job
     */
    public function store($id, Request $request)
    {
        $profile = $request->profile;
        $job = JobVacancy::where('status', 'Active')->findOrFail($id);

        $profile->bookmarks()->syncWithoutDetaching([$job->id]);

        return response()->json([
            'message' => 'Job saved successfully',
            'vacancy' => $this->formatVacancy($job->load(['organization', 'currency']))
        ], 201);
    }

    /**
     * DELETE /api/jobs/{id}/unsave
     * Remove a saved job
     */
    public function destroy($id, Request $request)
    {
        $profile = $request->profile;
        $profile->bookmarks()->detach($id);

        return response()->json(['message' => 'Job removed from saved']);
    }

    /**
     * Standard job vacancy formatter
     */
    private function formatVacancy($vacancy)
    {
        return [
            'id'           => $vacancy->id,
            'position'     => $vacancy->position,
            'field'        => $vacancy->field,
            'salary'       => $vacancy->salary,
            'currency'     => [
                'code'   => $vacancy->currency->code ?? null,
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
            'created_at'   => $vacancy->created_at,
            'updated_at'   => $vacancy->updated_at,
        ];
    }
}
