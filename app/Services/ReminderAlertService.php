<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Facades\Log;

class ReminderAlertService
{
    public function checkAndTriggerAlerts($companyId): void
    {
        $failedCount = Appointment::query()
            ->where('company_id', $companyId)
            ->where('reminder_status', 'failed')
            ->count();

        $highRetryCount = Appointment::query()
            ->where('company_id', $companyId)
            ->where('reminder_retry_count', '>=', 3)
            ->count();

        $stuckProcessing = Appointment::query()
            ->where('company_id', $companyId)
            ->where('reminder_status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(5))
            ->count();

        // 🚨 Alert conditions
        if ($failedCount >= 10) {
            $this->sendAlert("High failed reminders: {$failedCount}");
        }

        if ($highRetryCount >= 5) {
            $this->sendAlert("High retry reminders: {$highRetryCount}");
        }

        if ($stuckProcessing >= 5) {
            $this->sendAlert("Stuck processing reminders: {$stuckProcessing}");
        }
    }

    protected function sendAlert(string $message): void
    {
        // v1: log فقط
        Log::critical('[REMINDER ALERT] ' . $message);

        // v2 (بعدها): Slack / Email / WhatsApp admin
    }
}
