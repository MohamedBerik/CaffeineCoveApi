<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

class CompanyAccountingInitializer
{
    public static function init(int $companyId): void
    {
        DB::transaction(function () use ($companyId) {

            $defaults = [
                ['code' => '1000', 'name' => 'Cash / Bank',               'type' => 'asset'],
                ['code' => '1100', 'name' => 'Accounts Receivable',       'type' => 'asset'],
                ['code' => '2100', 'name' => 'Customer Credit / Advances', 'type' => 'liability'],
                ['code' => '4000', 'name' => 'Sales Revenue',             'type' => 'revenue'],
            ];

            foreach ($defaults as $row) {
                Account::updateOrCreate(
                    ['company_id' => $companyId, 'code' => $row['code']],
                    ['name' => $row['name'], 'type' => $row['type'], 'parent_id' => null]
                );
            }
        });
    }
}
