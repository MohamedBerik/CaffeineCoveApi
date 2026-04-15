<?php

namespace App\Services;

use App\Models\Account;
use App\Services\Tenant;
use Illuminate\Support\Facades\DB;

class CompanyAccountingInitializer
{
    /**
     * Standard chart of accounts for new company
     */
    const CHART_OF_ACCOUNTS = [
        // Assets (1000-1999)
        ['code' => '1000', 'name' => 'Cash on Hand', 'type' => Account::TYPE_ASSET],
        ['code' => '1010', 'name' => 'Petty Cash', 'type' => Account::TYPE_ASSET],
        ['code' => '1020', 'name' => 'Bank Account', 'type' => Account::TYPE_ASSET],
        ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => Account::TYPE_ASSET],
        ['code' => '1200', 'name' => 'Inventory', 'type' => Account::TYPE_ASSET],
        ['code' => '1300', 'name' => 'Prepaid Expenses', 'type' => Account::TYPE_ASSET],
        ['code' => '1400', 'name' => 'Fixed Assets', 'type' => Account::TYPE_ASSET],
        ['code' => '1410', 'name' => 'Accumulated Depreciation', 'type' => Account::TYPE_ASSET],

        // Liabilities (2000-2999)
        ['code' => '2000', 'name' => 'Accounts Payable', 'type' => Account::TYPE_LIABILITY],
        ['code' => '2100', 'name' => 'Customer Credit / Advances', 'type' => Account::TYPE_LIABILITY],
        ['code' => '2200', 'name' => 'Accrued Expenses', 'type' => Account::TYPE_LIABILITY],
        ['code' => '2300', 'name' => 'Loans Payable', 'type' => Account::TYPE_LIABILITY],

        // Equity (3000-3999)
        ['code' => '3000', 'name' => 'Owner\'s Capital', 'type' => Account::TYPE_EQUITY],
        ['code' => '3100', 'name' => 'Owner\'s Drawings', 'type' => Account::TYPE_EQUITY],
        ['code' => '3200', 'name' => 'Retained Earnings', 'type' => Account::TYPE_EQUITY],

        // Revenue (4000-4999)
        ['code' => '4000', 'name' => 'Consultation Revenue', 'type' => Account::TYPE_REVENUE],
        ['code' => '4010', 'name' => 'Treatment Revenue', 'type' => Account::TYPE_REVENUE],
        ['code' => '4020', 'name' => 'Product Sales', 'type' => Account::TYPE_REVENUE],
        ['code' => '4100', 'name' => 'Other Revenue', 'type' => Account::TYPE_REVENUE],

        // Expenses (5000-5999)
        ['code' => '5000', 'name' => 'Salaries and Wages', 'type' => Account::TYPE_EXPENSE],
        ['code' => '5010', 'name' => 'Rent Expense', 'type' => Account::TYPE_EXPENSE],
        ['code' => '5020', 'name' => 'Utilities Expense', 'type' => Account::TYPE_EXPENSE],
        ['code' => '5030', 'name' => 'Dental Supplies', 'type' => Account::TYPE_EXPENSE],
        ['code' => '5040', 'name' => 'Lab Fees', 'type' => Account::TYPE_EXPENSE],
        ['code' => '5050', 'name' => 'Marketing Expense', 'type' => Account::TYPE_EXPENSE],
        ['code' => '5060', 'name' => 'Insurance Expense', 'type' => Account::TYPE_EXPENSE],
        ['code' => '5070', 'name' => 'Professional Fees', 'type' => Account::TYPE_EXPENSE],
        ['code' => '5080', 'name' => 'Bank Charges', 'type' => Account::TYPE_EXPENSE],
        ['code' => '5090', 'name' => 'Depreciation Expense', 'type' => Account::TYPE_EXPENSE],
        ['code' => '5100', 'name' => 'Miscellaneous Expense', 'type' => Account::TYPE_EXPENSE],
    ];

    /**
     * Initialize chart of accounts for a company
     */
    public static function init(int $companyId): void
    {
        DB::transaction(function () use ($companyId) {
            foreach (self::CHART_OF_ACCOUNTS as $row) {
                Account::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'code' => $row['code'],
                    ],
                    [
                        'name' => $row['name'],
                        'type' => $row['type'],
                        'parent_id' => null,
                    ]
                );
            }

            // ✅ تسجيل النشاط
            ActivityLogger::log(
                $companyId,
                null,
                'accounting.initialized',
                'Company',
                $companyId,
                ['accounts_count' => count(self::CHART_OF_ACCOUNTS)]
            );
        });
    }

    /**
     * Initialize minimal chart of accounts (faster onboarding)
     */
    public static function initMinimal(int $companyId): void
    {
        $minimalAccounts = [
            ['code' => '1000', 'name' => 'Cash / Bank', 'type' => Account::TYPE_ASSET],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => Account::TYPE_ASSET],
            ['code' => '2100', 'name' => 'Customer Credit / Advances', 'type' => Account::TYPE_LIABILITY],
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => Account::TYPE_REVENUE],
            ['code' => '5000', 'name' => 'General Expenses', 'type' => Account::TYPE_EXPENSE],
        ];

        DB::transaction(function () use ($companyId, $minimalAccounts) {
            foreach ($minimalAccounts as $row) {
                Account::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'code' => $row['code'],
                    ],
                    [
                        'name' => $row['name'],
                        'type' => $row['type'],
                        'parent_id' => null,
                    ]
                );
            }
        });
    }

    /**
     * Check if company has accounting initialized
     */
    public static function isInitialized(int $companyId): bool
    {
        return Account::query()
            ->where('company_id', $companyId)
            ->exists();
    }

    /**
     * Initialize for current tenant
     */
    public static function initForCurrentTenant(): void
    {
        $companyId = Tenant::id();

        if (!$companyId) {
            throw new \Exception('No tenant context available');
        }

        self::init($companyId);
    }

    /**
     * Add custom account
     */
    public static function addCustomAccount(
        int $companyId,
        string $code,
        string $name,
        string $type,
        ?int $parentId = null
    ): Account {
        return Account::create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'parent_id' => $parentId,
        ]);
    }
}
