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
        \Log::info('EVENT CONSTRUCTED');

        $this->payload = $payload;
    }

    public function broadcastOn(): array
    {
        \Log::info('BROADCAST ON CALLED');

        return [
            new Channel('security-feed'),
        ];
    }

    public function broadcastAs(): string
    {
        \Log::info('BROADCAST AS CALLED');

        return 'security.updated';
    }

    public function broadcastWith(): array
    {
        \Log::info('BROADCAST WITH CALLED');

        return $this->payload;
    }
}
