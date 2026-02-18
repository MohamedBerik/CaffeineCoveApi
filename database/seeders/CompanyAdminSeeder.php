<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CompanyAdminSeeder extends Seeder
{
    public function run()
    {
        // admin الشركة الأولى
        User::updateOrCreate(
            ['email' => 'admin1@erp.test'],
            [
                'name' => 'Company 1 Admin',
                'password' => Hash::make('123456'),
                'company_id' => 1,
                'role' => 'admin',
                'status' => 'active',
                'is_super_admin' => false
            ]
        );

        // admin الشركة الثانية
        User::updateOrCreate(
            ['email' => 'admin2@erp.test'],
            [
                'name' => 'Company 2 Admin',
                'password' => Hash::make('123456'),
                'company_id' => 2,
                'role' => 'admin',
                'status' => 'active',
                'is_super_admin' => false
            ]
        );
    }
}
