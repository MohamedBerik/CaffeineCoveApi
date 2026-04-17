<?php
// app/Services/Tenant.php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class Tenant
{
    protected static $currentId = null;
    protected static $isSuperAdmin = null;
    protected static $currentCompany = null;

    /**
     * Set current tenant ID manually (for CLI/Jobs)
     */
    public static function setId(?int $companyId): void
    {
        static::$currentId = $companyId;
        static::$currentCompany = null; // Clear cached company
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
     * Get current company model
     */
    public static function company(): ?\App\Models\Company
    {
        $companyId = static::id();

        if (!$companyId) {
            return null;
        }

        if (static::$currentCompany && static::$currentCompany->id === $companyId) {
            return static::$currentCompany;
        }

        static::$currentCompany = \App\Models\Company::find($companyId);

        return static::$currentCompany;
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
        // ✅ Manual override
        if (static::$isSuperAdmin !== null) {
            return static::$isSuperAdmin;
        }

        // ✅ From Auth (مع فحص وجود المستخدم أولاً)
        if (Auth::check()) {
            $user = Auth::user();
            // ✅ استخدم property بدل method
            return $user ? (bool) ($user->is_super_admin ?? false) : false;
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
     * Check if current company is active
     */
    public static function isActive(): bool
    {
        $company = static::company();

        if (!$company) {
            return false;
        }

        return $company->status === \App\Models\Company::STATUS_ACTIVE;
    }

    /**
     * Check if current company is on trial
     */
    public static function isTrial(): bool
    {
        $company = static::company();

        if (!$company) {
            return false;
        }

        return $company->status === \App\Models\Company::STATUS_TRIAL;
    }

    /**
     * Check if current company is suspended
     */
    public static function isSuspended(): bool
    {
        $company = static::company();

        if (!$company) {
            return false;
        }

        return $company->status === \App\Models\Company::STATUS_SUSPENDED;
    }

    /**
     * Get current company status
     */
    public static function status(): ?string
    {
        return static::company()?->status;
    }

    /**
     * Get current company trial days left
     */
    public static function trialDaysLeft(): ?int
    {
        $company = static::company();

        if (!$company || !$company->trial_ends_at) {
            return null;
        }

        return now()->diffInDays($company->trial_ends_at, false);
    }

    /**
     * Check if trial has expired
     */
    public static function trialHasExpired(): bool
    {
        $daysLeft = static::trialDaysLeft();

        return $daysLeft !== null && $daysLeft < 0;
    }

    /**
     * Reset all manual overrides
     * ✅ MUST be called after every job/queue execution
     */
    public static function reset(): void
    {
        static::$currentId = null;
        static::$isSuperAdmin = null;
        static::$currentCompany = null;
    }

    /**
     * Run callback in super admin context
     */
    public static function asSuperAdmin(callable $callback)
    {
        $previousId = static::$currentId;
        $previousSuperAdmin = static::$isSuperAdmin;
        $previousCompany = static::$currentCompany;

        static::setIsSuperAdmin(true);
        static::setId(null);

        try {
            return $callback();
        } finally {
            static::$currentId = $previousId;
            static::$isSuperAdmin = $previousSuperAdmin;
            static::$currentCompany = $previousCompany;
        }
    }

    /**
     * Run callback for specific company
     */
    public static function forCompany(int $companyId, callable $callback)
    {
        $previousId = static::$currentId;
        $previousSuperAdmin = static::$isSuperAdmin;
        $previousCompany = static::$currentCompany;

        static::setId($companyId);
        static::setIsSuperAdmin(false);

        try {
            return $callback();
        } finally {
            static::$currentId = $previousId;
            static::$isSuperAdmin = $previousSuperAdmin;
            static::$currentCompany = $previousCompany;
        }
    }

    /**
     * Run callback for multiple companies (batch processing)
     */
    public static function forEachCompany(array $companyIds, callable $callback): array
    {
        $results = [];

        foreach ($companyIds as $companyId) {
            try {
                $results[$companyId] = static::forCompany($companyId, $callback);
            } catch (\Exception $e) {
                $results[$companyId] = [
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get all company IDs (for batch processing)
     */
    public static function getAllCompanyIds(?array $statuses = null): array
    {
        return static::asSuperAdmin(function () use ($statuses) {
            $query = \App\Models\Company::query();

            if ($statuses) {
                $query->whereIn('status', $statuses);
            }

            return $query->pluck('id')->toArray();
        });
    }

    /**
     * Get tenant context for logging/debugging
     */
    public static function context(): array
    {
        return [
            'company_id' => static::id(),
            'is_super_admin' => static::isSuperAdmin(),
            'company_status' => static::status(),
            'has_tenant' => static::hasTenant(),
        ];
    }

    /**
     * Ensure user has access to current company
     */
    public static function ensureAccess(): void
    {
        if (static::isSuperAdmin()) {
            return;
        }

        $user = Auth::user();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        if (!$user->company_id) {
            throw new \Exception('User not associated with any company');
        }

        if (static::id() !== $user->company_id) {
            throw new \Exception('Access denied to this company');
        }

        if (static::isSuspended()) {
            throw new \Exception('Company is suspended');
        }
    }
}
