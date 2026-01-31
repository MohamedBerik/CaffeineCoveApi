<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run()
    {
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $cashier = Role::firstOrCreate(['name' => 'cashier']);

        $perms = [
            'sales.manage',
            'inventory.manage',
            'purchases.manage',
            'finance.view',
            'users.manage',
        ];

        foreach ($perms as $p) {

            $perm = Permission::firstOrCreate([
                'name' => $p
            ]);

            // اربط الصلاحية بالـ admin لو مش مربوطة
            if (! $admin->permissions->contains($perm->id)) {
                $admin->permissions()->attach($perm->id);
            }
        }

        $cashierPerms = Permission::whereIn('name', [
            'sales.manage'
        ])->pluck('id');

        $cashier->permissions()->syncWithoutDetaching($cashierPerms);
    }
}
