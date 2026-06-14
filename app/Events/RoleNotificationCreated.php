<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public string $role;

    public array $notification;

    public function __construct(
        string $role,
        array $notification
    ) {
        $this->role = $role;
        $this->notification = $notification;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'role.' . $this->role
            )
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return $this->notification;
    }
}
