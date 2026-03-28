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

        $count = 0;

        foreach ($appointments as $appointment) {

            // 🛑 حماية إضافية
            if ($appointment->status !== 'scheduled') {
                continue;
            }

            $appointment->update([
                'status' => 'no_show',
                ...$this->markReminderNotNeeded(), // ✅ بدل app()
            ]);

            $count++;
        }

        $this->info("Marked {$count} appointments as no_show");

        return self::SUCCESS;
    }
}
