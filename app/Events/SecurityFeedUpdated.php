<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SecurityFeedUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('security-feed')
        ];
    }

    public function broadcastAs(): string
    {
        return 'security.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
