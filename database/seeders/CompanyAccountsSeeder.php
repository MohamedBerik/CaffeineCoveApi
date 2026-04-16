<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Services\CompanyAccountingInitializer;
use App\Services\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class CompanyAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding company accounts...');

        // ✅ اختيار: كل الشركات ولا النشطة فقط
        $companies = Company::whereIn('status', ['active', 'trial'])->get();

        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Run CompanySeeder first.');
            return;
        }

        $bar = $this->command->getOutput()->createProgressBar($companies->count());
        $bar->start();

        $created = 0;
        $skipped = 0;

        foreach ($companies as $company) {
            // ✅ استخدام Tenant Context للشركة الحالية
            Tenant::forCompany($company->id, function () use ($company, &$created, &$skipped) {
                $this->seedCompanyAccounts($company, $created, $skipped);
            });

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);
        $this->command->info("✅ Accounts seeded: {$created} created, {$skipped} skipped.");
    }

    /**
     * Seed accounts for a specific company.
     */
    private function seedCompanyAccounts(Company $company, int &$created, int &$skipped): void
    {
        // ✅ استخدام الـ Service بدل تكرار الكود
        $initializer = app(CompanyAccountingInitializer::class);

        // ✅ التحقق لو الحسابات موجودة بالفعل
        $hasAccounts = \App\Models\Account::query()
            ->where('company_id', $company->id)
            ->exists();

        if ($hasAccounts) {
            $skipped++;
            Log::debug("Accounts already exist for company: {$company->name} (ID: {$company->id})");
            return;
        }

        // ✅ إنشاء الحسابات
        $initializer->init($company->id);
        $created++;

        Log::info("Accounts seeded for company: {$company->name} (ID: {$company->id})");
    }
}
