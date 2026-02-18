<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    public function run()
    {
        Company::updateOrCreate(
            ['id' => 1],
            ['name' => 'Company One']
        );

        Company::updateOrCreate(
            ['id' => 2],
            ['name' => 'Company Two']
        );
    }
}
