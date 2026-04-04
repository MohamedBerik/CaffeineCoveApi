<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ReminderAlertService
{
    public function checkAndTriggerAlerts(int $companyId): void
    {
        // -----------------------------
        // Metrics
        // -----------------------------
        $failedCount = Appointment::query()
            ->where('company_id', $companyId)
            ->where('reminder_status', 'failed')
            ->where('updated_at', '>=', now()->subMinutes(10)) // recent only
            ->count();

        $highRetryCount = Appointment::query()
            ->where('company_id', $companyId)
            ->where('reminder_retry_count', '>=', 3)
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->count();

        $stuckProcessing = Appointment::query()
            ->where('company_id', $companyId)
            ->where('reminder_status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(5))
            ->count();

        // -----------------------------
        // Alerts (with dedup)
        // -----------------------------

        if ($failedCount >= 10) {
            $this->sendAlert(
                companyId: $companyId,
                type: 'failed',
                message: "High failed reminders",
                meta: ['count' => $failedCount]
            );
        }

        if ($highRetryCount >= 5) {
            $this->sendAlert(
                companyId: $companyId,
                type: 'retry',
                message: "High retry reminders",
                meta: ['count' => $highRetryCount]
            );
        }

        if ($stuckProcessing >= 5) {
            $this->sendAlert(
                companyId: $companyId,
                type: 'stuck',
                message: "Stuck processing reminders",
                meta: ['count' => $stuckProcessing]
            );
        }
    }

    protected function sendAlert(
        int $companyId,
        string $type,
        string $message,
        array $meta = []
    ): void {
        // -----------------------------
        // Deduplication key
        // -----------------------------
        $cacheKey = "reminder_alert_{$type}_company_{$companyId}";

        // لو اتبعت خلال آخر 10 دقايق → تجاهل
        if (Cache::has($cacheKey)) {
            return;
        }

        // -----------------------------
        // Log alert
        // -----------------------------
        Log::critical('[REMINDER ALERT]', [
            'company_id' => $companyId,
            'type' => $type,
            'message' => $message,
            'meta' => $meta,
            'triggered_at' => now()->toDateTimeString(),
        ]);

        // -----------------------------
        // Cooldown (10 minutes)
        // -----------------------------
        Cache::put($cacheKey, true, now()->addMinutes(10));

        // -----------------------------
        // Future integrations
        // -----------------------------
        // SlackNotification::send(...)
        // Mail::to(...)->send(...)
    }

    public function getDashboardAlerts(int $companyId): array
    {
        $alerts = [];

        // نفس الحسابات
        $recentFailed = Appointment::query()
            ->where('company_id', $companyId)
            ->where('reminder_status', 'failed')
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->count();

        $stuckProcessing = Appointment::query()
            ->where('company_id', $companyId)
            ->where('reminder_status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->count();

        $recentRetry = Appointment::query()
            ->where('company_id', $companyId)
            ->where('reminder_retry_count', '>=', 3)
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recentFailed >= 10) {
            $alerts[] = $this->buildAlert('failed', $recentFailed);
        }

        if ($stuckProcessing >= 5) {
            $alerts[] = $this->buildAlert('stuck', $stuckProcessing);
        }

        if ($recentRetry >= 5) {
            $alerts[] = $this->buildAlert('retry', $recentRetry);
        }

        return $alerts;
    }

    private function buildAlert(string $type, int $count): array
    {
        return match ($type) {
            'failed' => [
                'type' => 'danger',
                'priority' => 'high',
                'code' => 'REMINDER_FAILED_SPIKE',
                'message' => 'High failed reminders',
                'count' => $count,
            ],
            'stuck' => [
                'type' => 'warning',
                'priority' => 'medium',
                'code' => 'REMINDER_STUCK',
                'message' => 'Reminders stuck in processing',
                'count' => $count,
            ],
            'retry' => [
                'type' => 'warning',
                'priority' => 'medium',
                'code' => 'REMINDER_RETRY_HIGH',
                'message' => 'High retry reminders',
                'count' => $count,
            ],
        };
    }
}
