<?php

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AlertCreated implements ShouldBroadcast
{
    public $alert;

    public function __construct($alert)
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
}
