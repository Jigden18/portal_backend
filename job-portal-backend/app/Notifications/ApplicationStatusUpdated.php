<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use App\Models\JobApplication;

class ApplicationStatusUpdated extends Notification
{
    use Queueable;

    protected $application;

    public function __construct(JobApplication $application)
    {
        $this->application = $application;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast']; // store in DB and broadcast
    }

    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'job_title'      => $this->application->job->position,
            'status'         => $this->application->status,
            'message'        => $this->application->message,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'application_id' => $this->application->id,
            'job_title'      => $this->application->job->position,
            'status'         => $this->application->status,
            'message'        => $this->application->message,
            'updated_at' => now()->toDateTimeString(),
        ]);
    }
}
