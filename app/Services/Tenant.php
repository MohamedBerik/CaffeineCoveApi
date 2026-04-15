<?php
// app/Services/Tenant.php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class Tenant
{
    protected static $currentId = null;
    protected static $isSuperAdmin = null;

    /**
     * Set current tenant ID manually (for CLI/Jobs)
     */
    public static function setId(?int $companyId): void
    {
        static::$currentId = $companyId;
    }

    /**
     * Get current tenant ID
     */
    public static function id(): ?int
    {
        if (static::$currentId !== null) {
            return static::$currentId;
        }

        if (Auth::check()) {
            return Auth::user()->company_id;
        }

        return null;
    }

    /**
     * Set super admin flag manually
     */
    public static function setIsSuperAdmin(?bool $isSuperAdmin): void
    {
        static::$isSuperAdmin = $isSuperAdmin;
    }

    /**
     * Check if current context is super admin
     */
    public static function isSuperAdmin(): bool
    {
        if (static::$isSuperAdmin !== null) {
            return static::$isSuperAdmin;
        }

        if (Auth::check()) {
            return Auth::user()->isSuperAdmin() ?? false;
        }

        return false;
    }

    /**
     * Check if we're in a valid tenant context
     */
    public static function hasTenant(): bool
    {
        return static::id() !== null;
    }

    /**
     * Reset all manual overrides
     * ✅ MUST be called after every job/queue execution
     */
    public static function reset(): void
    {
        static::$currentId = null;
        static::$isSuperAdmin = null;
    }

    /**
     * Run callback in super admin context
     */
    public static function asSuperAdmin(callable $callback)
    {
        $previousId = static::$currentId;
        $previousSuperAdmin = static::$isSuperAdmin;

        static::setIsSuperAdmin(true);
        static::setId(null);

        try {
            return $callback();
        } finally {
            static::reset(); // ✅ استخدام reset بدل restore
        }
    }

    /**
     * Run callback for specific company
     */
    public static function forCompany(int $companyId, callable $callback)
    {
        $previousId = static::$currentId;
        $previousSuperAdmin = static::$isSuperAdmin;

        static::setId($companyId);
        static::setIsSuperAdmin(false);

        try {
            return $callback();
        } finally {
            static::reset(); // ✅ استخدام reset بدل restore
        }
    }
}
