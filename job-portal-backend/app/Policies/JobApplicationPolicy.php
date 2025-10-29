<?php

namespace App\Policies;

use App\Models\JobApplication;
use App\Models\User;

class JobApplicationPolicy
{
    /**
     * Ensure only the owning organization can update an application's status.
     */
    public function updateStatus(User $user, JobApplication $application): bool
    {
        // Check if the user belongs to the organization that owns this job
        $organization = $application->job->organization;

        return $organization && $organization->user_id === $user->id;
    }
}
