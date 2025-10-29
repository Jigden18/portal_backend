<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class IncomingCall implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $fromUserId;
    public $toUserId;
    public $channelName;
    public $token;
    public $uid;

    public function __construct($fromUserId, $toUserId, $channelName, $token, $uid)
    {
        $this->fromUserId = $fromUserId;
        $this->toUserId = $toUserId;
        $this->channelName = $channelName;
        $this->token = $token;
        $this->uid = $uid;
    }

    public function broadcastOn()
    {
        // Send event privately to the receiver
        return new PrivateChannel('user.' . $this->toUserId);
    }

    public function broadcastAs()
    {
        return 'incoming.call';
    }
}
