<?php

namespace App\Events;

use App\Models\SystemAlert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $alert;

    public function __construct(SystemAlert $alert)
    {
        $this->alert = $alert;
    }

    public function broadcastOn()
    {
        return new Channel('alerts');
    }

    public function broadcastAs()
    {
        return 'alert.created';
    }

    public function broadcastWith()
    {
        return [
            'alert' => [
                'id' => $this->alert->id,
                'message' => $this->alert->message,
                'priority' => $this->alert->priority,
                'type' => $this->alert->type,
                'time' => $this->alert->triggered_at,
                'read' => $this->alert->acknowledged_at !== null,
            ]
        ];
    }
}
