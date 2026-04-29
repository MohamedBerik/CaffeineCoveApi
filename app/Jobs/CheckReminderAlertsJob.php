<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\ReminderAlertService;
use App\Jobs\Concerns\ResetsTenantContext;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
// ❌ تم حذف use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckReminderAlertsJob implements ShouldQueue, ShouldBeUnique
{
    // ✅ استخدمنا Dispatchable, InteractsWithQueue, SerializesModels فقط (متوافق مع Laravel 8)
    use Dispatchable, InteractsWithQueue, SerializesModels;
    use ResetsTenantContext;

    public $tries = 3;
    public $timeout = 600;

    public function backoff(): array
    {
        return [60, 300, 600];
    }

    public function uniqueId(): string
    {
        return 'check_reminder_alerts';
    }

    public function uniqueFor(): int
    {
        return 900;
    }

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $this->process();
    }

    protected function process(): void
    {
        $companies = Company::whereIn('status', ['active', 'trial'])
            ->select(['id', 'name', 'status'])
            ->cursor();

        $totalProcessed = 0;
        $totalFailed = 0;

        Log::info('Starting reminder alerts check');

        foreach ($companies as $company) {
            try {
                Tenant::forCompany($company->id, function () use ($company) {
                    Log::debug('Checking reminders for company', [
                        'company_id' => $company->id,
                        'company_name' => $company->name
                    ]);

                    app(ReminderAlertService::class)
                        ->checkAndTriggerAlerts($company->id);
                });

                $totalProcessed++;

                if ($totalProcessed % 10 === 0) {
                    usleep(100000);
                }
            } catch (\Exception $e) {
                $totalFailed++;

                Log::error('Failed to check reminders for company', [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                continue;
            }
        }

        Log::info('Completed reminder alerts check', [
            'total_processed' => $totalProcessed,
            'total_failed' => $totalFailed
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('CheckReminderAlertsJob failed completely', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
