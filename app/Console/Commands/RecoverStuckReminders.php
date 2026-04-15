<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Company;
use App\Services\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RecoverStuckReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reminders:recover-stuck {--company= : Specific company ID}';

    /**
     * The console command description.
     */
    protected $description = 'Recover stuck reminders that have been processing for too long';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Recovering stuck reminders...');

        // ✅ تحديد الشركات المستهدفة
        if ($companyId = $this->option('company')) {
            $companies = Company::where('id', $companyId)->get();
        } else {
            $companies = Company::whereIn('status', ['active', 'trial'])->get();
        }

        if ($companies->isEmpty()) {
            $this->info('No companies found to process.');
            return Command::SUCCESS;
        }

        $totalRecovered = 0;

        // ✅ معالجة كل شركة على حدة
        foreach ($companies as $company) {
            $this->line("Processing company: {$company->name} (ID: {$company->id})");

            // ✅ تعيين Tenant Context للشركة الحالية
            Tenant::setId($company->id);

            $recovered = $this->recoverForCompany($company->id);
            $totalRecovered += $recovered;

            $this->line("  - Recovered {$recovered} stuck reminders.");

            // ✅ تنظيف الـ Context
            Tenant::reset();
        }

        $this->info("Total recovered: {$totalRecovered} stuck reminders across " . $companies->count() . " companies.");

        return Command::SUCCESS;
    }

    /**
     * استعادة التذكيرات العالقة لشركة محددة
     */
    private function recoverForCompany(int $companyId): int
    {
        return Appointment::query()
            ->where('reminder_status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update([
                'reminder_status' => 'pending',
                'next_reminder_at' => now()->addMinute(),
            ]);
    }
}
