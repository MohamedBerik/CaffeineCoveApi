<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InsightGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $companyId;
    public $insight;

    public function __construct($companyId, $insight)
    {
        $this->companyId = $companyId;
        $this->insight = $insight;
    }

    public function broadcastOn()
    {
        return new Channel('company.' . $this->companyId);
    }

    public function broadcastAs()
    {
        return 'insight.generated';
    }

    public function broadcastWith()
    {
        return [
            'insight' => $this->insight,
        ];
    }
}
