<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;

class MarkNoShowAppointments extends Command
{
    protected $signature = 'appointments:mark-no-show';
    protected $description = 'Mark overdue scheduled appointments as no_show';

    public function handle(): int
    {
        $now = now();

        $appointments = Appointment::query()
            ->where('status', 'scheduled')
            ->whereRaw(
                "TIMESTAMP(appointment_date, appointment_time) < ?",
                [$now->copy()->subMinutes(30)]
            )
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->limit(100)
            ->get();

        if ($appointments->isEmpty()) {
            $this->info('No appointments to mark as no_show');
            return self::SUCCESS;
        }

        foreach ($appointments as $appointment) {

            // 🛑 حماية من التكرار
            if ($appointment->status !== 'scheduled') {
                continue;
            }

            $appointment->update([
                'status' => 'no_show',
                ...app(\App\Traits\HandlesAppointmentReminders::class)->markReminderNotNeeded(),
            ]);
        }

        $this->info("Marked {$appointments->count()} appointments as no_show");

        return self::SUCCESS;
    }
}
