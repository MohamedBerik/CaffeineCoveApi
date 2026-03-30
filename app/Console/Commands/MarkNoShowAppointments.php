<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use App\Traits\HandlesAppointmentReminders;

class MarkNoShowAppointments extends Command
{
    use HandlesAppointmentReminders; // ✅ الحل هنا

    protected $signature = 'appointments:mark-no-show';
    protected $description = 'Mark overdue scheduled appointments as no_show';

    public function handle(): int
    {
        $appointments = Appointment::query()
            ->where('status', 'scheduled')
            ->whereRaw(
                "TIMESTAMP(appointment_date, appointment_time) < ?",
                [now()->subMinutes(30)]
            )
            ->limit(100)
            ->get();

        foreach ($appointments as $appointment) {
            $appointment->update([
                'status' => 'no_show',
                ...$this->markReminderNotNeeded(),
            ]);
        }

        $this->info("Marked {$appointments->count()} appointments as no_show");

        return self::SUCCESS;
    }
}
