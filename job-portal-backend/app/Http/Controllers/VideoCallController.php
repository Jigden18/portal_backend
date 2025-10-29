<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JobApplication;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\Carbon;
use TaylanUnutmaz\AgoraTokenBuilder\RtcTokenBuilder;
use App\Events\IncomingCall;

class VideoCallController extends Controller
{
    /**
     * Scheduled Interview: Fetch token and channel info
     */
    public function getInterview(Request $request, $applicationId)
    {
        $application = JobApplication::with(['job.organization.user', 'jobseeker.user'])
            ->findOrFail($applicationId);

        $user = $request->user();
        $orgUserId = $application->job->organization->user->id;
        $jobseekerUserId = $application->jobseeker->user->id;

        if (!in_array($user->id, [$orgUserId, $jobseekerUserId])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($application->status !== 'Scheduled for interview') {
            return response()->json(['success' => false, 'message' => 'No interview scheduled'], 404);
        }

        $today = Carbon::today();
        $interviewDate = Carbon::parse($application->interview_date);

        if ($today->lt($interviewDate)) {
            return response()->json([
                'success' => false,
                'message' => 'Interview not yet active'
            ], 403);
        }

        $channelName = 'interview_' . $application->id;
        $uid = $user->id;
        $expireAt = Carbon::now()->timestamp + env('AGORA_TOKEN_EXPIRY', 3600);

        // Corrected: pass role as integer (1 = publisher)
        $token = RtcTokenBuilder::buildTokenWithUid(
            env('AGORA_APP_ID'),
            env('AGORA_APP_CERTIFICATE'),
            $channelName,
            $uid,
            1,            // Publisher role
            $expireAt
        );

        return response()->json([
            'success' => true,
            'data' => [
                'job_title' => $application->job->position,
                'interview_date' => $application->interview_date,
                'interview_time' => $application->interview_time,
                'channel_name' => $channelName,
                'token' => $token,
                'uid' => $uid,
                'expires_in' => env('AGORA_TOKEN_EXPIRY', 3600),
            ]
        ]);
    }

   /**
     * Start a user-to-user video call (on-demand)
     */
    public function startCall(Request $request, $conversationId)
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        // Verify the user is part of the conversation
        if (!in_array($user->id, [$conversation->user1_id, $conversation->user2_id])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $receiverId = $conversation->user1_id === $user->id
            ? $conversation->user2_id
            : $conversation->user1_id;

        $channelName = 'call_conv_' . $conversation->id;
        $uid = $user->id;
        $expireAt = Carbon::now()->timestamp + env('AGORA_TOKEN_EXPIRY', 3600);

        // Generate Agora token
        $token = RtcTokenBuilder::buildTokenWithUid(
            env('AGORA_APP_ID'),
            env('AGORA_APP_CERTIFICATE'),
            $channelName,
            $uid,
            1,
            $expireAt
        );

        // Store a call start message
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'message' => 'Video call started',
            'type' => 'video_call_start',
        ]);

        $conversation->touch();

        // Fire Pusher event to notify receiver in real-time
        event(new IncomingCall(
            $user->id,
            $receiverId,
            $channelName,
            $token,
            $uid
        ));

        return response()->json([
            'success' => true,
            'message' => 'Video call session created successfully',
            'data' => [
                'channel_name' => $channelName,
                'token' => $token,
                'uid' => $uid,
                'receiver_id' => $receiverId,
                'expires_in' => env('AGORA_TOKEN_EXPIRY', 3600),
            ]
        ]);
    }

    /**
     * End video call and log it
     */
    public function endCall(Request $request, $conversationId)
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (!in_array($user->id, [$conversation->user1_id, $conversation->user2_id])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'message' => 'Video call ended',
            'type' => 'video_call_end',
        ]);

        $conversation->touch();

        return response()->json([
            'success' => true,
            'message' => 'Video call ended successfully',
        ]);
    }
}