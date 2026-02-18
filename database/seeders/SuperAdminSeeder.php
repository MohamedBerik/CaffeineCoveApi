<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run()
    {
        User::updateOrCreate(
            ['email' => 'super@erp.test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('123456'),
                'is_super_admin' => true,
                'company_id' => null,
                'role' => 'super_admin',
                'status' => 'active'
            ]
        );
    }
}
