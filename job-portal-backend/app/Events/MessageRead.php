<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        // Load relations for consistent sender info
        $this->message = $message->load('sender.organization', 'sender.profile');
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversation.' . $this->message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'message-read';
    }

    public function broadcastWith(): array
    {
        $sender = $this->message->sender;

        $displayName = $sender->organization->name
            ?? $sender->profile->full_name
            ?? $sender->email
            ?? 'Unknown';

        $displayEmail = $sender->organization->email
            ?? $sender->profile->email
            ?? $sender->email
            ?? '';

        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $displayName,
            'sender_email' => $displayEmail,
            'message' => $this->message->message,
            'type' => $this->message->type,
            'payload' => $this->message->payload,
            'created_at' => $this->message->created_at->toDateTimeString(),
            'read_at' => now()->toDateTimeString(),
        ];
    }
}
