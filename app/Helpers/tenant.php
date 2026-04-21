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
    }

    /**
     * ✅ Set super admin context for CLI/Tinker
     */
    public static function asSuperAdmin(): void
    {
        Tenant::setId(null);
        Tenant::setIsSuperAdmin(true);
    }

    /**
     * ✅ Reset tenant context
     */
    public static function reset(): void
    {
        Tenant::reset();
    }
}
