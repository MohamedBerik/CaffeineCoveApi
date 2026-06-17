<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UnauthorizedRouteAccess
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ?int $userId,
        public string $route,
        public string $method,
        public string $ip,
        public array $context = []
    ) {}
}
