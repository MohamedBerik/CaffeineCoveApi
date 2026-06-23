<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\SystemAlert;
use App\Services\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Events\AlertCreated;

class ReminderAlertService
{
    /**
     * Alert type constants
     */
    const TYPE_FAILED = 'failed';
    const TYPE_RETRY = 'retry';
    const TYPE_STUCK = 'stuck';

    /**
     * Thresholds
     */
    const FAILED_THRESHOLD = 10;
    const RETRY_THRESHOLD = 5;
    const STUCK_THRESHOLD = 5;

    /**
     * Check and trigger alerts for a company
     */
    public function checkAndTriggerAlerts(?int $companyId = null): void
    {
        $companyId = $companyId ?? Tenant::id();

        if (!$companyId) {
            return;
        }

        // ✅ بدون where('company_id') - الـ Scope هيضيفه
        $failedCount = Appointment::query()
            ->where('reminder_status', 'failed')
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->count();

        $highRetryCount = Appointment::query()
            ->where('reminder_retry_count', '>=', 3)
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->count();

        $stuckProcessing = Appointment::query()
            ->where('reminder_status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(5))
            ->count();

        // ✅ Trigger alerts
        if ($failedCount >= self::FAILED_THRESHOLD) {
            $this->sendAlert(
                $companyId,
                self::TYPE_FAILED,
                "High failed reminders ({$failedCount} failures)",
                ['count' => $failedCount, 'threshold' => self::FAILED_THRESHOLD]
            );
        }

        if ($highRetryCount >= self::RETRY_THRESHOLD) {
            $this->sendAlert(
                $companyId,
                self::TYPE_RETRY,
                "High retry reminders ({$highRetryCount} retries)",
                ['count' => $highRetryCount, 'threshold' => self::RETRY_THRESHOLD]
            );
        }

        if ($stuckProcessing >= self::STUCK_THRESHOLD) {
            $this->sendAlert(
                $companyId,
                self::TYPE_STUCK,
                "Stuck processing reminders ({$stuckProcessing} stuck)",
                ['count' => $stuckProcessing, 'threshold' => self::STUCK_THRESHOLD]
            );
        }

        // ✅ Resolve if recovered
        $this->resolveIfRecovered($companyId, self::TYPE_FAILED, $failedCount < self::FAILED_THRESHOLD);
        $this->resolveIfRecovered($companyId, self::TYPE_RETRY, $highRetryCount < self::RETRY_THRESHOLD);
        $this->resolveIfRecovered($companyId, self::TYPE_STUCK, $stuckProcessing < self::STUCK_THRESHOLD);
    }

    /**
     * Check alerts for all active companies
     */
    public function checkAllCompanies(): array
    {
        return Tenant::asSuperAdmin(function () {
            $companies = \App\Models\Company::query()
                ->whereIn('status', ['active', 'trial'])
                ->get();

            $results = [];

            foreach ($companies as $company) {
                try {
                    $this->checkAndTriggerAlerts($company->id);
                    $results[$company->id] = ['status' => 'success'];
                } catch (\Exception $e) {
                    Log::error('Failed to check reminders for company', [
                        'company_id' => $company->id,
                        'error' => $e->getMessage(),
                    ]);
                    $results[$company->id] = ['status' => 'failed', 'error' => $e->getMessage()];
                }
            }

            return $results;
        });
    }

    /**
     * Send an alert
     */
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

        // ✅ بدون where('company_id')
        $existing = SystemAlert::query()
            ->where('code', $type)
            ->whereNull('resolved_at')
            ->first();

        if ($existing) {
            $existing->update([
                'meta' => array_merge($existing->meta ?? [], $meta),
                'updated_at' => now(),
            ]);

            Cache::put($cacheKey, true, now()->addMinutes(10));
            return;
        }

        $config = $this->getAlertConfig($type);

        $admins = AlertRecipientService::admins($companyId);

        AlertService::send(
            recipients: $admins,
            companyId: $companyId,
            branchId: Tenant::branchId(),
            message: $message,
            type: $config['type'],
            priority: $config['priority'],
            code: $type,
            meta: $meta
        );

        Log::warning('[REMINDER ALERT]', [
            'company_id' => $companyId,
            'type' => $type,
            'message' => $message,
            'meta' => $meta,
        ]);

        Cache::put($cacheKey, true, now()->addMinutes(10));
    }

    /**
     * Resolve alert if recovered
     */
    protected function resolveIfRecovered(int $companyId, string $type, bool $recovered): void
    {
        if (!$recovered) {
            return;
        }

        // ✅ بدون where('company_id')
        $updated = SystemAlert::query()
            ->where('code', $type)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        if ($updated > 0) {
            Log::info('[REMINDER ALERT RESOLVED]', [
                'company_id' => $companyId,
                'type' => $type,
            ]);

            // ✅ Clear cache when resolved
            Cache::forget("reminder_alert_{$type}_company_{$companyId}");
        }
    }

    /**
     * Get alert configuration by type
     */
    private function getAlertConfig(string $type): array
    {
        return match ($type) {
            self::TYPE_FAILED => [
                'type' => 'danger',
                'priority' => SystemAlert::PRIORITY_HIGH,
            ],
            self::TYPE_STUCK => [
                'type' => 'warning',
                'priority' => SystemAlert::PRIORITY_MEDIUM,
            ],
            self::TYPE_RETRY => [
                'type' => 'warning',
                'priority' => SystemAlert::PRIORITY_MEDIUM,
            ],
            default => [
                'type' => 'info',
                'priority' => SystemAlert::PRIORITY_LOW,
            ],
        };
    }

    /**
     * Get current reminder statistics for a company
     */
    public function getStatistics(?int $companyId = null): array
    {
        $companyId = $companyId ?? Tenant::id();

        if (!$companyId) {
            return [];
        }

        // ✅ بدون where('company_id')
        return [
            'failed_count' => Appointment::query()
                ->where('reminder_status', 'failed')
                ->where('updated_at', '>=', now()->subMinutes(10))
                ->count(),
            'retry_count' => Appointment::query()
                ->where('reminder_retry_count', '>=', 3)
                ->where('updated_at', '>=', now()->subMinutes(10))
                ->count(),
            'stuck_count' => Appointment::query()
                ->where('reminder_status', 'processing')
                ->where('updated_at', '<', now()->subMinutes(5))
                ->count(),
            'pending_count' => Appointment::query()
                ->where('reminder_status', 'pending')
                ->count(),
            'processing_count' => Appointment::query()
                ->where('reminder_status', 'processing')
                ->count(),
            'sent_count' => Appointment::query()
                ->where('reminder_status', 'sent')
                ->whereDate('last_reminder_at', today())
                ->count(),
            'active_alerts' => SystemAlert::query()
                ->whereIn('code', [
                    self::TYPE_FAILED,
                    self::TYPE_RETRY,
                    self::TYPE_STUCK,
                ])
                ->whereNull('resolved_at')
                ->count(),
        ];
    }

    /**
     * Manually resolve all reminder alerts for a company
     */
    public function resolveAllAlerts(?int $companyId = null): int
    {
        $companyId = $companyId ?? Tenant::id();

        if (!$companyId) {
            return 0;
        }

        $updated = SystemAlert::query()
            ->whereIn('code', [self::TYPE_FAILED, self::TYPE_RETRY, self::TYPE_STUCK])
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        // ✅ Clear all related caches
        foreach ([self::TYPE_FAILED, self::TYPE_RETRY, self::TYPE_STUCK] as $type) {
            Cache::forget("reminder_alert_{$type}_company_{$companyId}");
        }

        return $updated;
    }
}
