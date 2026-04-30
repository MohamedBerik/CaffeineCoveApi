<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'finance.view',
            'finance.create',
            'orders.view',
            'orders.manage',
            'appointments.view',
            'appointments.manage',
            'patients.view',
            'patients.manage',
            'treatment_plans.view',
            'treatment_plans.manage',
            'procedures.view',
            'procedures.manage',
            'reports.view',
            'activity_logs.view',
            'inventory.view',
            'inventory.manage',
            'purchases.manage',
            'purchases.receive',
            'purchases.return',
            'sales.view',
            'sales.manage',
            'reservations.view',
            'reservations.manage',
            'doctors.view',
            'doctors.manage',
            'employees.view',
            'employees.manage',
            'settings.view',
            'settings.manage',
            'radiology.view',
            'radiology.manage',
            'dental_records.view',
            'dental_records.create',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
        }

        // Create roles and assign permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $adminRole->syncPermissions($permissions);

        $doctorRole = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'api']);
        $doctorRole->syncPermissions([
            'appointments.view',
            'patients.view',
            'treatment_plans.view',
            'procedures.view',
            'dental_records.view',
        ]);

        $receptionistRole = Role::firstOrCreate(['name' => 'receptionist', 'guard_name' => 'api']);
        $receptionistRole->syncPermissions([
            'appointments.view',
            'appointments.manage',
            'patients.view',
            'patients.manage',
            'invoices.view',
        ]);
    }
}
