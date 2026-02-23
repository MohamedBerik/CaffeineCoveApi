<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Account;

class DefaultAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['code' => '1000', 'name' => 'Cash / Bank',                 'type' => 'asset'],
            ['code' => '1100', 'name' => 'Accounts Receivable',         'type' => 'asset'],
            ['code' => '2100', 'name' => 'Customer Credit / Advances',  'type' => 'liability'],
            // (اختياري لكن مهم للمحاسبة)
            ['code' => '4000', 'name' => 'Sales Revenue',               'type' => 'revenue'],
        ];

        Company::query()->chunkById(200, function ($companies) use ($defaults) {
            foreach ($companies as $company) {
                foreach ($defaults as $row) {
                    Account::updateOrCreate(
                        ['company_id' => $company->id, 'code' => $row['code']],
                        ['name' => $row['name'], 'type' => $row['type'], 'parent_id' => null]
                    );
                }
            }
        });
    }
}
