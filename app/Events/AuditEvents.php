<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FailedLogin
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $email;
    public $ip;
    public $reason;

    public function __construct(string $email, string $ip, string $reason = 'Invalid credentials')
    {
        $this->email = $email;
        $this->ip = $ip;
        $this->reason = $reason;
    }
}

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

class AdminOverride
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $adminId;
    public $action;
    public $targetId;
    public $context;

    public function __construct(int $adminId, string $action, ?int $targetId, array $context = [])
    {
        $this->adminId = $adminId;
        $this->action = $action;
        $this->targetId = $targetId;
        $this->context = $context;
    }
}
