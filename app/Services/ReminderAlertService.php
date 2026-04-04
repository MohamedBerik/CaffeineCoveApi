<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\SystemAlert;
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

        if ($failedCount < 10) {
            SystemAlert::query()
                ->where('company_id', $companyId)
                ->where('code', 'failed')
                ->whereNull('resolved_at')
                ->update([
                    'resolved_at' => now()
                ]);
        }

        if ($highRetryCount < 5) {
            SystemAlert::query()
                ->where('company_id', $companyId)
                ->where('code', 'retry')
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now()]);
        }

        if ($stuckProcessing < 5) {
            SystemAlert::query()
                ->where('company_id', $companyId)
                ->where('code', 'stuck')
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now()]);
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

        if (Cache::has($cacheKey)) {
            return;
        }

        // -----------------------------
        // Log alert
        // -----------------------------
        $existing = SystemAlert::query()
            ->where('company_id', $companyId)
            ->where('code', $type)
            ->whereNull('resolved_at')
            ->first();

        if ($existing) {
            return;
        }

        $config = $this->getAlertConfig($type);

        SystemAlert::create([
            'company_id' => $companyId,
            'code' => $type,
            'type' => $config['type'],
            'priority' => $config['priority'],
            'message' => $message,
            'meta' => $meta,
            'triggered_at' => now(),
        ]);

        Log::critical('[REMINDER ALERT]', [
            'company_id' => $companyId,
            'type' => $type,
            'message' => $message,
            'meta' => $meta,
            'triggered_at' => now()->toDateTimeString(),
        ]);

        Cache::put($cacheKey, true, now()->addMinutes(10));
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
                'meta' => ['count' => $count],
                'time' => now()->toIso8601String(),
            ],
            'stuck' => [
                'type' => 'warning',
                'priority' => 'medium',
                'code' => 'REMINDER_STUCK',
                'message' => 'Reminders stuck in processing',
                'meta' => ['count' => $count],
                'time' => now()->toIso8601String(),
            ],
            'retry' => [
                'type' => 'warning',
                'priority' => 'medium',
                'code' => 'REMINDER_RETRY_HIGH',
                'message' => 'High retry reminders',
                'meta' => ['count' => $count],
                'time' => now()->toIso8601String(),
            ],
        };
    }

    private function getAlertConfig(string $type): array
    {
        return match ($type) {
            'failed' => ['type' => 'danger', 'priority' => 'high'],
            'stuck' => ['type' => 'warning', 'priority' => 'medium'],
            'retry' => ['type' => 'warning', 'priority' => 'medium'],
        };
    }
}
