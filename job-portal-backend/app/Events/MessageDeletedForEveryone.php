<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeletedForEveryone implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversationId;
    public $messageId;
    public $deleterId;

    public function __construct(Message $message)
    {
        // Ensure the required data is available
        $this->conversationId = $message->conversation_id;
        $this->messageId = $message->id;
        $this->deleterId = $message->sender_id;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversation.' . $this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'message-deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id'   => $this->messageId,
            'conversation_id' => $this->conversationId,
            'deleter_id'   => $this->deleterId,
            'deleted_at'   => now()->toDateTimeString(),
        ];
    }
}
