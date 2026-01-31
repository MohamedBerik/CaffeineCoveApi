<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $admin = Role::create(['name' => 'admin']);
        $cashier = Role::create(['name' => 'cashier']);

        $perms = [
            'sales.manage',
            'inventory.manage',
            'purchases.manage',
            'finance.view',
            'users.manage',
        ];

        foreach ($perms as $p) {
            $perm = Permission::create(['name' => $p]);
            $admin->permissions()->attach($perm);
        }

        $cashier->permissions()->attach(
            Permission::whereIn('name', [
                'sales.manage'
            ])->pluck('id')
        );
    }
}
