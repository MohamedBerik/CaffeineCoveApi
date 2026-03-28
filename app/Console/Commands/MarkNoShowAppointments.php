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
            ->where(function ($q) use ($now) {
                $q->whereRaw(
                    "TIMESTAMP(appointment_date, appointment_time) < ?",
                    [$now->copy()->subMinutes(30)]
                );
            })
            ->limit(100)
            ->get();

        if ($appointments->isEmpty()) {
            $this->info('No appointments to mark as no_show');
            return self::SUCCESS;
        }

        foreach ($appointments as $appointment) {
            $appointment->update([
                'status' => 'no_show',
                'reminder_status' => 'not_needed',
            ]);
        }

        $this->info("Marked {$appointments->count()} appointments as no_show");

        return self::SUCCESS;
    }
}
