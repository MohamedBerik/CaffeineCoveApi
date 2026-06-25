<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class AlertRecipientService
{
    public static function user(int $userId): Collection
    {
        return User::query()
            ->whereKey($userId)
            ->get();
    }

    public static function users(array $userIds): Collection
    {
        return User::query()
            ->whereIn('id', $userIds)
            ->get();
    }

    public static function admins(int $companyId): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('role', 'admin')
            ->get();
    }

    public static function role(
        int $companyId,
        string $role
    ): Collection {
        return User::query()
            ->where('company_id', $companyId)
            ->where('role', $role)
            ->get();
    }

    public static function roles(
        int $companyId,
        array $roles
    ): Collection {
        return User::query()
            ->where('company_id', $companyId)
            ->whereIn('role', $roles)
            ->get();
    }

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
        string $alertCode,
        array $roles = []
    ): Collection {
        $query = User::query()
            ->where('company_id', $companyId);

        if (!empty($roles)) {
            $query->whereIn('role', $roles);
        }

        return $query
            ->where(function ($q) use ($alertCode) {

                $q->whereDoesntHave(
                    'alertPreferences',
                    function ($pref) use ($alertCode) {
                        $pref->where('alert_code', $alertCode);
                    }
                )

                    ->orWhereHas(
                        'alertPreferences',
                        function ($pref) use ($alertCode) {
                            $pref->where('alert_code', $alertCode)
                                ->where('enabled', true);
                        }
                    );
            })
            ->get();
    }

    public static function recipients(
        int $companyId,
        ?int $branchId,
        string $alertCode,
        array $roles = []
    ): Collection {
        $query = User::query()
            ->where('company_id', $companyId);

        if ($branchId) {
            $query->where(function ($q) use ($branchId) {
                $q->where('role', 'admin')
                    ->orWhere('branch_id', $branchId);
            });
        }

        if (!empty($roles)) {
            $query->where(function ($q) use ($roles) {
                $q->where('role', 'admin')
                    ->orWhereIn('role', $roles);
            });
        }

        return $query
            ->where(function ($q) use ($alertCode) {
                $q->whereDoesntHave('alertPreferences')
                    ->orWhereHas('alertPreferences', function ($q) use ($alertCode) {
                        $q->where('alert_code', $alertCode)
                            ->where('enabled', true);
                    });
            })
            ->get();
    }
}
