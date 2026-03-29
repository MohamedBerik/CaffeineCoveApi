<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;
use App\Jobs\SendAppointmentFollowUpJob;

class SendDueFollowUps extends Command
{
    protected $signature = 'appointments:send-followups';

    protected $description = 'Send follow-up messages to patients after completed appointments (with retry support)';

    public function handle(): int
    {
        $limit = 50;

        $appointments = Appointment::query()
            ->where('status', 'completed')
            ->where(function ($q) {

                // 🟢 pending (first attempt)
                $q->where(function ($q) {
                    $q->where('follow_up_status', 'pending')
                        ->whereNotNull('follow_up_at')
                        ->where('follow_up_at', '<=', now());
                })

                    // 🔁 retry (failed with conditions)
                    ->orWhere(function ($q) {
                        $q->where('follow_up_status', 'failed')
                            ->where('follow_up_retry_count', '<', 3)
                            ->whereNotNull('follow_up_next_retry_at')
                            ->where('follow_up_next_retry_at', '<=', now());
                    });
            })

            // 🧠 ترتيب الأولويات:
            // pending الأول، بعده retry
            ->orderByRaw("
                CASE
                    WHEN follow_up_status = 'pending' THEN 1
                    WHEN follow_up_status = 'failed' THEN 2
                    ELSE 3
                END
            ")

            // 🟢 pending حسب follow_up_at
            ->orderBy('follow_up_at', 'asc')

            // 🔁 retry حسب next_retry_at
            ->orderBy('follow_up_next_retry_at', 'asc')

            ->limit($limit)
            ->get(['id']);

        if ($appointments->isEmpty()) {
            $this->info('No follow-ups due.');
            return self::SUCCESS;
        }

        foreach ($appointments as $appointment) {
            SendAppointmentFollowUpJob::dispatch($appointment->id);
        }

        $this->info("Dispatched {$appointments->count()} follow-up job(s).");

        return self::SUCCESS;
    }
}
