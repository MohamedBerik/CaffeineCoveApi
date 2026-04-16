<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    /**
     * All permissions in the system.
     */
    protected array $allPermissions = [
        // Finance
        'finance.view',
        'finance.create',

        // Orders
        'orders.view',
        'orders.manage',
        'orders.confirm',
        'orders.cancel',

        // Payments
        'payments.refund',

        // Purchases
        'purchases.manage',
        'purchases.receive',
        'purchases.return',

        // Appointments
        'appointments.view',
        'appointments.manage',
        'appointments.complete',

        // Treatment Plans
        'treatment_plans.view',
        'treatment_plans.manage',

        // Procedures
        'procedures.view',
        'procedures.manage',

        // Patients
        'patients.view',
        'patients.manage',

        // Reports
        'reports.view',
        'reports.export',

        // Settings
        'settings.view',
        'settings.manage',

        // Users
        'users.manage',

        // Inventory (legacy)
        'inventory.manage',
        'sales.manage',
    ];

    /**
     * Role definitions with their permissions.
     */
    protected array $roleDefinitions = [
        'admin' => [
            'finance.view',
            'finance.create',
            'orders.view',
            'orders.manage',
            'orders.confirm',
            'orders.cancel',
            'payments.refund',
            'purchases.manage',
            'purchases.receive',
            'purchases.return',
            'appointments.view',
            'appointments.manage',
            'appointments.complete',
            'treatment_plans.view',
            'treatment_plans.manage',
            'procedures.view',
            'procedures.manage',
            'patients.view',
            'patients.manage',
            'reports.view',
            'reports.export',
            'settings.view',
            'settings.manage',
            'users.manage',
            'inventory.manage',
            'sales.manage',
        ],
        'doctor' => [
            'appointments.view',
            'appointments.manage',
            'appointments.complete',
            'treatment_plans.view',
            'procedures.view',
            'patients.view',
        ],
        'receptionist' => [
            'appointments.view',
            'appointments.manage',
            'patients.view',
            'patients.manage',
            'finance.view',
        ],
        'cashier' => [
            'finance.view',
            'finance.create',
            'orders.view',
            'payments.refund',
            'sales.manage',
        ],
        'user' => [
            'appointments.view',
            'patients.view',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔐 Seeding roles and permissions...');

        // 1. Create all permissions
        $this->createAllPermissions();

        // 2. Create roles and assign permissions
        $this->createRoles();

        $this->command->info('✅ RBAC seeded successfully!');
        $this->displaySummary();
    }

    /**
     * Create all permissions in the system.
     */
    private function createAllPermissions(): void
    {
        $created = 0;
        $existed = 0;

        foreach ($this->allPermissions as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                [
                    'guard_name' => 'web',
                    'module' => $this->getModuleFromPermission($permissionName),
                ]
            );

            if ($permission->wasRecentlyCreated) {
                $created++;
            } else {
                $existed++;
            }
        }

        $this->command->line("   Permissions: {$created} created, {$existed} already existed.");
    }

    /**
     * Create roles and assign permissions.
     */
    private function createRoles(): void
    {
        foreach ($this->roleDefinitions as $roleName => $permissions) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                ['guard_name' => 'web']
            );

            $permIds = Permission::whereIn('name', $permissions)->pluck('id');
            $role->permissions()->sync($permIds);

            $this->command->line("   ✅ {$roleName}: " . count($permissions) . " permissions");
        }
    }

    /**
     * Extract module name from permission.
     */
    private function getModuleFromPermission(string $permission): string
    {
        if (str_contains($permission, '.')) {
            return explode('.', $permission)[0];
        }
        return 'general';
    }

    /**
     * Display seeding summary.
     */
    private function displaySummary(): void
    {
        $this->command->newLine();
        $this->command->info('📋 RBAC Summary:');
        $this->command->line('   Roles created: ' . count($this->roleDefinitions));
        $this->command->line('   Total permissions: ' . count($this->allPermissions));
    }
}
