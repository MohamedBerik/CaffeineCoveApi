<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $companyId;
    public $type;
    public $data;

    public function __construct($companyId, $type, $data)
    {
        $this->companyId = $companyId;
        $this->type = $type;
        $this->data = $data;
    }

    public function broadcastOn()
    {
        return new Channel('company.' . $this->companyId);
    }

    public function broadcastAs()
    {
        return 'dashboard.updated';
    }

    public function broadcastWith()
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}
