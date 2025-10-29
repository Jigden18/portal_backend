<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\JobApplication;
use App\Events\IncomingCall;
use Carbon\Carbon;
use TaylanUnutmaz\AgoraTokenBuilder\RtcTokenBuilder;

class NotifyScheduledInterviews extends Command
{
    protected $signature = 'interviews:notify';
    protected $description = 'Notify users of upcoming scheduled interviews';

    public function handle()
    {
        $now = Carbon::now();

        $upcoming = JobApplication::with(['job.organization.user', 'jobseeker.user'])
            ->where('status', 'Scheduled for interview')
            ->whereDate('interview_date', $now->toDateString())
            ->whereTime('interview_time', '<=', $now->format('H:i'))
            ->get();

        foreach ($upcoming as $app) {
            $orgUser = $app->job->organization->user;
            $seekerUser = $app->jobseeker->user;
            $channelName = 'interview_' . $app->id;

            $expireAt = Carbon::now()->timestamp + env('AGORA_TOKEN_EXPIRY', 3600);

            $orgToken = RtcTokenBuilder::buildTokenWithUid(
                env('AGORA_APP_ID'),
                env('AGORA_APP_CERTIFICATE'),
                $channelName,
                $orgUser->id,
                1, // Publisher role
                $expireAt
            );

            $seekerToken = RtcTokenBuilder::buildTokenWithUid(
                env('AGORA_APP_ID'),
                env('AGORA_APP_CERTIFICATE'),
                $channelName,
                $seekerUser->id,
                1, // Publisher role
                $expireAt
            );

            event(new IncomingCall($seekerUser->id, $orgUser->id, $channelName, $orgToken, $orgUser->id));
            event(new IncomingCall($orgUser->id, $seekerUser->id, $channelName, $seekerToken, $seekerUser->id));
        }

        return 0;
    }
}
