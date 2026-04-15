<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\ReminderAlertService;
use App\Jobs\Concerns\ResetsTenantContext;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckReminderAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use ResetsTenantContext;

    public $timeout = 600;
    public $tries = 1;

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
        $companies = Company::whereIn('status', ['active', 'trial'])->get(['id', 'name', 'status']);

        Log::info('Starting reminder alerts check', [
            'total_companies' => $companies->count()
        ]);

        foreach ($companies as $company) {
            try {
                Tenant::forCompany($company->id, function () use ($company) {
                    Log::info('Checking reminders for company', [
                        'company_id' => $company->id,
                        'company_name' => $company->name
                    ]);

                    app(ReminderAlertService::class)
                        ->checkAndTriggerAlerts($company->id);
                });
            } catch (\Exception $e) {
                Log::error('Failed to check reminders for company', [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        Log::info('Completed reminder alerts check for all companies');
    }
}
