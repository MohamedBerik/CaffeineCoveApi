<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Account;

class CompanyAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {

            Account::firstOrCreate(
                ['company_id' => $company->id, 'code' => '1000'],
                ['name' => 'Cash / Bank', 'type' => 'asset', 'parent_id' => null]
            );

            Account::firstOrCreate(
                ['company_id' => $company->id, 'code' => '1100'],
                ['name' => 'Accounts Receivable', 'type' => 'asset', 'parent_id' => null]
            );
        }
    }
}
