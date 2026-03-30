<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminderJob;
use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendDueAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-due-reminders {--limit=100}';
    protected $description = 'Dispatch reminder jobs for appointments whose reminders are due';

    public function handle(): int
    {
        $limit = max((int) $this->option('limit'), 1);

        $appointments = DB::transaction(function () use ($limit) {

            $appointments = Appointment::query()
                ->where('status', 'scheduled')
                ->where('reminder_status', 'pending')
                ->whereNotNull('next_reminder_at')
                ->where('next_reminder_at', '<=', now())
                ->lockForUpdate()
                ->limit($limit)
                ->get();

            foreach ($appointments as $appointment) {
                $appointment->update([
                    'reminder_status' => 'processing'
                ]);
            }

            return $appointments;
        });

        if ($appointments->isEmpty()) {
            $this->info('No due appointment reminders found.');
            return self::SUCCESS;
        }

        foreach ($appointments as $appointment) {
            SendAppointmentReminderJob::dispatch($appointment->id);
        }

        $this->info("Dispatched {$appointments->count()} reminder job(s).");

        return self::SUCCESS;
    }
}
