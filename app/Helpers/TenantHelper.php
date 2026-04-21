<?php

namespace App\Helpers;

use App\Services\Tenant;

class TenantHelper
{
    /**
     * ✅ Set tenant context for CLI/Tinker
     */
    public static function forCompany(int $companyId): void
    {
        Tenant::setId($companyId);
        Tenant::setIsSuperAdmin(false);

        // ✅ Feedback للمستخدم
        echo "✅ Tenant context set to Company ID: {$companyId}\n";
    }

    /**
     * ✅ Set super admin context for CLI/Tinker
     */
    public static function asSuperAdmin(): void
    {
        Tenant::setId(null);
        Tenant::setIsSuperAdmin(true);

        // ✅ Feedback للمستخدم
        echo "✅ Super Admin context activated (Global Mode)\n";
    }

    /**
     * ✅ Reset tenant context
     */
    public static function reset(): void
    {
        Tenant::reset();

        // ✅ Feedback للمستخدم
        echo "✅ Tenant context reset\n";
    }

    /**
     * ✅ Get current tenant context info
     */
    public static function info(): array
    {
        return [
            'company_id' => Tenant::id(),
            'is_super_admin' => Tenant::isSuperAdmin(),
            'has_tenant' => Tenant::hasTenant(),
        ];
    }

    /**
     * ✅ Display current tenant context
     */
    public static function show(): void
    {
        $info = self::info();

        echo "┌─────────────────────────────────────┐\n";
        echo "│ Current Tenant Context              │\n";
        echo "├─────────────────────────────────────┤\n";
        echo "│ Company ID: " . str_pad($info['company_id'] ?? 'null', 24) . " │\n";
        echo "│ Super Admin: " . str_pad($info['is_super_admin'] ? 'Yes' : 'No', 22) . " │\n";
        echo "│ Has Tenant:  " . str_pad($info['has_tenant'] ? 'Yes' : 'No', 22) . " │\n";
        echo "└─────────────────────────────────────┘\n";
    }

    /**
     * ✅ Run callback in company context
     */
    public static function withCompany(int $companyId, callable $callback)
    {
        return Tenant::forCompany($companyId, $callback);
    }

    /**
     * ✅ Run callback in super admin context
     */
    public static function withSuperAdmin(callable $callback)
    {
        return Tenant::asSuperAdmin($callback);
    }
}
