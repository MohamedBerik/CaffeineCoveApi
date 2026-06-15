<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminOverride
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $adminId;
    public $action;
    public $targetId;
    public $context;

    public function __construct(
        int $adminId,
        string $action,
        ?int $targetId,
        array $context = []
    ) {
        $this->adminId = $adminId;
        $this->action = $action;
        $this->targetId = $targetId;
        $this->context = $context;
    }
}
