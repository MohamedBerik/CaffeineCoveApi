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
