<?php

namespace App\Services;

use App\Constants\AlertCodes;
use App\Models\Appointment;
use App\Models\SystemAlert;
use App\Services\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\AlertTemplateService;
use App\Services\AlertDefinitionService;

class ReminderAlertService
{
    const FAILED_THRESHOLD = 10;
    const RETRY_THRESHOLD = 5;
    const STUCK_THRESHOLD = 5;

    public function checkAndTriggerAlerts(?int $companyId = null): void
    {
        $companyId = $companyId ?? Tenant::id();

        if (!$companyId) return;

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

        // REMINDER_FAILED
        if ($failedCount >= self::FAILED_THRESHOLD) {
            $data = [
                'count'     => $failedCount,
                'threshold' => self::FAILED_THRESHOLD,
            ];

            $this->sendAlert(
                $companyId,
                AlertCodes::REMINDER_FAILED,
                AlertTemplateService::render(AlertCodes::REMINDER_FAILED, $data),
                $data
            );
        }

        // REMINDER_RETRY
        if ($highRetryCount >= self::RETRY_THRESHOLD) {
            $data = [
                'count'     => $highRetryCount,
                'threshold' => self::RETRY_THRESHOLD,
            ];

            $this->sendAlert(
                $companyId,
                AlertCodes::REMINDER_RETRY,
                AlertTemplateService::render(AlertCodes::REMINDER_RETRY, $data),
                $data
            );
        }

        // REMINDER_STUCK
        if ($stuckProcessing >= self::STUCK_THRESHOLD) {
            $data = [
                'count'     => $stuckProcessing,
                'threshold' => self::STUCK_THRESHOLD,
            ];

            $this->sendAlert(
                $companyId,
                AlertCodes::REMINDER_STUCK,
                AlertTemplateService::render(AlertCodes::REMINDER_STUCK, $data),
                $data
            );
        }

        $this->resolveIfRecovered($companyId, AlertCodes::REMINDER_FAILED, $failedCount < self::FAILED_THRESHOLD);
        $this->resolveIfRecovered($companyId, AlertCodes::REMINDER_RETRY, $highRetryCount < self::RETRY_THRESHOLD);
        $this->resolveIfRecovered($companyId, AlertCodes::REMINDER_STUCK, $stuckProcessing < self::STUCK_THRESHOLD);
    }

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
                        'error'      => $e->getMessage(),
                    ]);
                    $results[$company->id] = ['status' => 'failed', 'error' => $e->getMessage()];
                }
            }

            return $results;
        });
    }

    protected function sendAlert(
        int $companyId,
        string $type,
        string $message,
        array $meta = []
    ): void {
        $cacheKey = "reminder_alert_{$type}_company_{$companyId}";

        if (Cache::has($cacheKey)) return;

        $existing = SystemAlert::query()
            ->where('code', $type)
            ->whereNull('resolved_at')
            ->first();

        if ($existing) {
            $existing->update([
                'meta'       => array_merge($existing->meta ?? [], $meta),
                'updated_at' => now(),
            ]);
            Cache::put($cacheKey, true, now()->addMinutes(10));
            return;
        }

        $config = AlertDefinitionService::definition($type);
        $roles  = $config['roles'];

        $recipients = AlertRecipientService::recipients(
            companyId: $companyId,
            branchId: Tenant::branchId(),
            alertCode: $type,
            roles: $roles
        );

        AlertService::send(
            recipients: $recipients,
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
            'type'       => $type,
            'message'    => $message,
            'meta'       => $meta,
        ]);

        Cache::put($cacheKey, true, now()->addMinutes(10));
    }

    protected function resolveIfRecovered(int $companyId, string $type, bool $recovered): void
    {
        if (!$recovered) return;

        $updated = SystemAlert::query()
            ->where('code', $type)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        if ($updated > 0) {
            Log::info('[REMINDER ALERT RESOLVED]', [
                'company_id' => $companyId,
                'type'       => $type,
            ]);
            Cache::forget("reminder_alert_{$type}_company_{$companyId}");
        }
    }

    public function getStatistics(?int $companyId = null): array
    {
        $companyId = $companyId ?? Tenant::id();
        if (!$companyId) return [];

        $codes = [
            AlertCodes::REMINDER_FAILED,
            AlertCodes::REMINDER_RETRY,
            AlertCodes::REMINDER_STUCK,
        ];

        return [
            'failed_count'     => Appointment::query()->where('reminder_status', 'failed')->where('updated_at', '>=', now()->subMinutes(10))->count(),
            'retry_count'      => Appointment::query()->where('reminder_retry_count', '>=', 3)->where('updated_at', '>=', now()->subMinutes(10))->count(),
            'stuck_count'       => Appointment::query()->where('reminder_status', 'processing')->where('updated_at', '<', now()->subMinutes(5))->count(),
            'pending_count'     => Appointment::query()->where('reminder_status', 'pending')->count(),
            'processing_count'  => Appointment::query()->where('reminder_status', 'processing')->count(),
            'sent_count'        => Appointment::query()->where('reminder_status', 'sent')->whereDate('last_reminder_at', today())->count(),
            'active_alerts'     => SystemAlert::query()->whereIn('code', $codes)->whereNull('resolved_at')->count(),
        ];
    }

    public function resolveAllAlerts(?int $companyId = null): int
    {
        $companyId = $companyId ?? Tenant::id();
        if (!$companyId) return 0;

        $codes = [
            AlertCodes::REMINDER_FAILED,
            AlertCodes::REMINDER_RETRY,
            AlertCodes::REMINDER_STUCK,
        ];

        $updated = SystemAlert::query()->whereIn('code', $codes)->whereNull('resolved_at')->update(['resolved_at' => now()]);

        foreach ($codes as $code) {
            Cache::forget("reminder_alert_{$code}_company_{$companyId}");
        }

        return $updated;
    }
}
