<?php

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentReminderCrashed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $appointmentId,
        public string $error
    ) {}
}
