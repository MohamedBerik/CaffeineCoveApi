<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SystemExceptionOccurred
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $message,
        public string $exception,
        public string $file,
        public int $line
    ) {}
}
