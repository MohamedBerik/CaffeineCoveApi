<?php

use App\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentReminderSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Appointment $appointment
    ) {}
}
