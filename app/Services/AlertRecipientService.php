<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class AlertRecipientService
{
    /**
     * مستخدم واحد
     */
    public static function user(int $userId): Collection
    {
        return User::query()
            ->whereKey($userId)
            ->get();
    }

    /**
     * مجموعة مستخدمين
     */
    public static function users(array $userIds): Collection
    {
        return User::query()
            ->whereIn('id', $userIds)
            ->get();
    }

    /**
     * كل Admins الشركة
     */
    public static function admins(int $companyId): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('role', 'admin')
            ->get();
    }

    /**
     * حسب Role معين
     */
    public static function role(
        int $companyId,
        string $role
    ): Collection {
        return User::query()
            ->where('company_id', $companyId)
            ->where('role', $role)
            ->get();
    }

    /**
     * حسب عدة Roles
     */
    public static function roles(
        int $companyId,
        array $roles
    ): Collection {
        return User::query()
            ->where('company_id', $companyId)
            ->whereIn('role', $roles)
            ->get();
    }

    /**
     * كل مستخدمي فرع معين
     */
    public static function branch(
        int $companyId,
        int $branchId
    ): Collection {
        return User::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->get();
    }

    public static function subscribed(
        int $companyId,
        string $code,
        array $roles = ['admin']
    ) {
        return User::query()
            ->where('company_id', $companyId)
            ->whereIn('role', $roles)
            ->where(function ($q) use ($code) {
                $q->whereDoesntHave('alertPreferences')
                    ->orWhereHas('alertPreferences', function ($q) use ($code) {
                        $q->where('alert_code', $code)
                            ->where('enabled', true);
                    });
            })
            ->get();
    }
}
