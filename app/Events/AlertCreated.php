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
        logger()->info('Alert broadcasting', [
            'alert_id' => $this->alert->id,
            'company_id' => $this->alert->company_id,
            'branch_id' => $this->alert->branch_id,
            'user_id' => $this->alert->user_id,
        ]);

        $channels = [];

        if ($this->alert->user_id) {
            $channels[] = new PrivateChannel(
                'user.' . $this->alert->user_id
            );

            return $channels;
        }

        if ($this->alert->branch_id) {
            $channels[] = new PrivateChannel(
                'company.'
                    . $this->alert->company_id
                    . '.branch.'
                    . $this->alert->branch_id
                    . '.alerts'
            );
        }

        return $channels;
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
