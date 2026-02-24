<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetAccountingForCompany extends Command
{
    protected $signature = 'reset:accounting {company_id}';
    protected $description = 'Reset invoices, payments, refunds, journals for a company';

    public function handle()
    {
        $companyId = (int) $this->argument('company_id');

        DB::transaction(function () use ($companyId) {

            // Refunds
            DB::table('payment_refunds')->where('company_id', $companyId)->delete();

            // Payments
            DB::table('payments')->where('company_id', $companyId)->delete();

            // Journal entries
            $entryIds = DB::table('journal_entries')
                ->where('company_id', $companyId)
                ->pluck('id');

            DB::table('journal_lines')
                ->whereIn('journal_entry_id', $entryIds)
                ->delete();

            DB::table('journal_entries')
                ->where('company_id', $companyId)
                ->delete();

            // Customer ledger
            DB::table('customer_ledger_entries')
                ->where('company_id', $companyId)
                ->delete();

            // Invoices
            $invoiceIds = DB::table('invoices')
                ->where('company_id', $companyId)
                ->pluck('id');

            DB::table('invoice_items')
                ->whereIn('invoice_id', $invoiceIds)
                ->delete();

            DB::table('invoices')
                ->where('company_id', $companyId)
                ->delete();

            // Orders → رجّعها pending
            DB::table('orders')
                ->where('company_id', $companyId)
                ->update(['status' => 'pending']);
        });

        $this->info("Accounting reset completed for company_id={$companyId}");
    }
}
