<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public array $data
    ) {}

    public function broadcastOn()
    {
        return [
            new PrivateChannel(
                'user.' . $this->userId
            )
        ];
    }

    public function broadcastAs()
    {
        return 'notification.created';
    }

    public function broadcastWith()
    {
        return $this->data;
    }
}
