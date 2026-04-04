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
        // بعد الحسابات مباشرة
        $this->resolveIfRecovered($companyId, 'failed', $failedCount < 10);
        $this->resolveIfRecovered($companyId, 'retry', $highRetryCount < 5);
        $this->resolveIfRecovered($companyId, 'stuck', $stuckProcessing < 5);
    }

    protected function sendAlert(
        int $companyId,
        string $type,
        string $message,
        array $meta = []
    ): void {

        $cacheKey = "reminder_alert_{$type}_company_{$companyId}";

        if (Cache::has($cacheKey)) {
            return;
        }

        $existing = SystemAlert::query()
            ->where('company_id', $companyId)
            ->where('code', $type)
            ->whereNull('resolved_at')
            ->first();

        if ($existing) {
            $existing->update([
                'meta' => $meta,
                'updated_at' => now(),
            ]);
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
        ]);

        Cache::put($cacheKey, true, now()->addMinutes(10));
    }

    protected function resolveIfRecovered(int $companyId, string $type, bool $recovered): void
    {
        if (!$recovered) {
            return;
        }

        SystemAlert::query()
            ->where('company_id', $companyId)
            ->where('code', $type)
            ->whereNull('resolved_at')
            ->update([
                'resolved_at' => now()
            ]);
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
