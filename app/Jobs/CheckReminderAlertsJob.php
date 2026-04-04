<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\ReminderAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Queue\Queueable;

class CheckReminderAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $companies = Company::pluck('id');

        foreach ($companies as $companyId) {
            app(ReminderAlertService::class)
                ->checkAndTriggerAlerts($companyId);
        }
    }
}
