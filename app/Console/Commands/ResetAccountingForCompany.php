<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetAccountingForCompany extends Command
{
    protected $signature = 'reset:accounting
                            {company_id : The ID of the company}
                            {--force : Skip confirmation prompt}
                            {--keep-orders : Do not reset order status}';

    protected $description = 'Reset invoices, payments, refunds, and journals for a specific company';

    public function handle(): int
    {
        $companyId = (int) $this->argument('company_id');

        // ✅ 1. التحقق من وجود الشركة
        $company = Company::find($companyId);

        if (!$company) {
            $this->error("Company with ID {$companyId} not found.");
            return Command::FAILURE;
        }

        // ✅ 2. منع تنفيذ الأمر في بيئة الإنتاج بدون --force
        if (app()->environment('production') && !$this->option('force')) {
            $this->error('This command cannot be run in production without --force flag.');
            $this->line('If you are absolutely sure, run: php artisan reset:accounting ' . $companyId . ' --force');
            return Command::FAILURE;
        }

        // ✅ 3. عرض تحذير وتأكيد العملية
        $this->warn("⚠️  WARNING: This will permanently delete all accounting data for:");
        $this->line("   Company: {$company->name} (ID: {$companyId})");
        $this->line("   Status: {$company->status}");
        $this->line("");
        $this->line("The following will be deleted:");
        $this->line("   - Payment Refunds");
        $this->line("   - Payments");
        $this->line("   - Journal Entries & Lines");
        $this->line("   - Customer Ledger Entries");
        $this->line("   - Invoices & Invoice Items");

        if (!$this->option('keep-orders')) {
            $this->line("   - Orders (status reset to 'pending')");
        }

        if (!$this->option('force') && !$this->confirm('Do you really want to continue?')) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        // ✅ 4. تنفيذ العملية في Tenant Context
        $this->info("Resetting accounting data for {$company->name}...");

        Tenant::forCompany($companyId, function () use ($companyId) {
            DB::transaction(function () use ($companyId) {
                $deleted = $this->performReset($companyId);
                $this->line("   - Deleted {$deleted['refunds']} refunds");
                $this->line("   - Deleted {$deleted['payments']} payments");
                $this->line("   - Deleted {$deleted['journals']} journal entries");
                $this->line("   - Deleted {$deleted['ledger']} ledger entries");
                $this->line("   - Deleted {$deleted['invoices']} invoices");
            });
        });

        $this->newLine();
        $this->info("✅ Accounting reset completed for company: {$company->name} (ID: {$companyId})");

        // ✅ 5. تسجيل العملية في الـ Log
        \Log::warning('Accounting data reset', [
            'company_id' => $companyId,
            'company_name' => $company->name,
            'executed_by' => get_current_user() ?? 'cli',
            'force' => $this->option('force'),
        ]);

        return Command::SUCCESS;
    }

    /**
     * تنفيذ عملية المسح وإرجاع عدد السجلات المحذوفة
     */
    private function performReset(int $companyId): array
    {
        // Refunds
        $refundsDeleted = DB::table('payment_refunds')
            ->where('company_id', $companyId)
            ->delete();

        // Payments
        $paymentsDeleted = DB::table('payments')
            ->where('company_id', $companyId)
            ->delete();

        // Journal entries
        $entryIds = DB::table('journal_entries')
            ->where('company_id', $companyId)
            ->pluck('id');

        DB::table('journal_lines')
            ->whereIn('journal_entry_id', $entryIds)
            ->delete();

        $journalsDeleted = DB::table('journal_entries')
            ->where('company_id', $companyId)
            ->delete();

        // Customer ledger
        $ledgerDeleted = DB::table('customer_ledger_entries')
            ->where('company_id', $companyId)
            ->delete();

        // Invoices
        $invoiceIds = DB::table('invoices')
            ->where('company_id', $companyId)
            ->pluck('id');

        DB::table('invoice_items')
            ->whereIn('invoice_id', $invoiceIds)
            ->delete();

        $invoicesDeleted = DB::table('invoices')
            ->where('company_id', $companyId)
            ->delete();

        // Orders → رجّعها pending (اختياري)
        if (!$this->option('keep-orders')) {
            DB::table('orders')
                ->where('company_id', $companyId)
                ->update(['status' => 'pending']);
        }

        return [
            'refunds' => $refundsDeleted,
            'payments' => $paymentsDeleted,
            'journals' => $journalsDeleted,
            'ledger' => $ledgerDeleted,
            'invoices' => $invoicesDeleted,
        ];
    }
}
