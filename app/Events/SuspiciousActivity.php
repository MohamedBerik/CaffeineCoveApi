<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SuspiciousActivity
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $action;
    public $context;

    public function __construct(?int $userId, string $action, array $context = [])
    {
        $this->userId = $userId;
        $this->action = $action;
        $this->context = $context;
    }
}
