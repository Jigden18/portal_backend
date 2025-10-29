<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobVacancy;
use App\Models\JobApplication;
use App\Notifications\ApplicationStatusUpdated; // Import the notification

class OrganizationApplicationController extends Controller
{
     use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
     
    /**
     * List all applications for a specific job
     */
    public function index(Request $request, $jobId)
    {
        $organization = $request->organization; // from middleware
        $job = JobVacancy::where('organization_id', $organization->id)->findOrFail($jobId);

        // Fetch applications with profile
        $applications = JobApplication::with('jobseeker')
            ->where('job_id', $job->id)
            ->get();

        return response()->json([
            'job' => [
                'id' => $job->id,
                'position' => $job->position,
            ],
            'applications' => $applications->map(function ($app) {
                return [
                    'id'         => $app->id,
                    'status'     => $app->status,
                    'pdf'        => $app->pdf_path,
                    'applied_at' => $app->created_at,
                    'jobseeker'  => [
                        'id'        => $app->jobseeker->id,
                        'full_name' => $app->jobseeker->full_name,
                        'email'     => $app->jobseeker->email,
                        'photo'     => $app->jobseeker->photo_url,
                    ]
                ];
            })
        ]);
    }

    /**
     * Show a single application details
     */
    public function show(Request $request, $applicationId)
    {
        $organization = $request->organization;

        // Ensure this application belongs to the organization's job
        $application = JobApplication::with(['jobseeker', 'job'])
            ->findOrFail($applicationId);

        if ($application->job->organization_id !== $organization->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'id'     => $application->id,
            'status' => $application->status,
            'pdf'    => $application->pdf_path,
            'applied_at' => $application->created_at,
            'job' => [
                'id'       => $application->job->id,
                'position' => $application->job->position,
            ],
            'jobseeker' => [
                'id'        => $application->jobseeker->id,
                'full_name' => $application->jobseeker->full_name,
                'email'     => $application->jobseeker->email,
                'photo'     => $application->jobseeker->photo_url,
                'address'   => $application->jobseeker->address,
                'occupation'=> $application->jobseeker->occupation,
            ]
        ]);
    }

    /**
     * Update the status of a job application
     */
    public function updateStatus(Request $request, $applicationId)
    {
        $application = JobApplication::with([
            'job.organization.user',  // Ensure we load user for organization
            'jobseeker.user',         // Ensure we load user for jobseeker
            'job'
        ])->findOrFail($applicationId);

        // Authorize using policy
        $this->authorize('updateStatus', $application);

        // Validate new status
        $validated = $request->validate([
            'status'  => 'required|in:Accepted,Rejected,Scheduled for interview,Pending',
            'message' => 'nullable|string|max:500',
            'interview_date' => 'nullable|date|after_or_equal:today',
            'interview_time' => 'nullable|date_format:H:i',
        ]);

        // Update application
        $updateData = [
            'status' => $validated['status'],
            'message' => $validated['message'] ?? null,
        ];

        // Only set interview fields if status is "Scheduled for interview"
        if ($validated['status'] === 'Scheduled for interview') {
            if (empty($validated['interview_date']) || empty($validated['interview_time'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Interview date and time are required when scheduling an interview.'
                ], 422);
            }
            $updateData['interview_date'] = $validated['interview_date'];
            $updateData['interview_time'] = $validated['interview_time'];

            // Auto-generate message if not provided
            if (empty($updateData['message'])) {
                $updateData['message'] = "Interview scheduled for {$validated['interview_date']} at {$validated['interview_time']}";
            }
        }

        $application->update($updateData);

        // Notify the jobseeker (profile must use Notifiable trait)
        $application->jobseeker->notify(new ApplicationStatusUpdated($application));

        // Log to chat system

        // Retrieve correct user IDs for chat
        $orgUserId = $application->job->organization->user->id; // correct user ID for organization
        $jobseekerUserId = $application->jobseeker->user->id;   // correct user ID for jobseeker

        // Find or create conversation between org user and jobseeker
        $conversation = \App\Models\Conversation::findOrCreateBetween($orgUserId, $jobseekerUserId);

        // Create automated status update message
        $statusMessage = "Your application for **{$application->job->position}** has been updated to **{$application->status}**.";

        \App\Models\Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $orgUserId,
            'message'         => $statusMessage,
            'type'            => 'status_update',
        ]);

        // Optional: add personal note
        if (!empty($validated['message'])) {
            \App\Models\Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $orgUserId,
                'message'         => $validated['message'],
                'type'            => 'user_message',
            ]);
        }

        // Update conversation timestamp
        $conversation->touch();

        return response()->json([
            'success' => true,
            'message' => 'Application status updated and logged to chat.',
            'data'    => $application,
        ]);
    }
}
