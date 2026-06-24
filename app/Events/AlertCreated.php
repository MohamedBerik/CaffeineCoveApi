<?php

namespace App\Events;

use App\Models\SystemAlert;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public SystemAlert $alert;

    /**
     * Create a new event instance.
     */
    public function __construct(SystemAlert $alert)
    {
        $this->alert = $alert;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'user.' . $this->alert->user_id
            )
        ];
    }
    /**
     * Event name used by Echo.
     */
    public function broadcastAs(): string
    {
        return 'alert.created';
    }

    /**
     * Payload sent to the frontend.
     */
    public function broadcastWith(): array
    {
        return [
            'id'       => $this->alert->id,
            'message'  => $this->alert->message,
            'priority' => $this->alert->priority,
        ];
    }
}
