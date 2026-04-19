<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Auth\Access\HandlesAuthorization;

class ActivityLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function view(User $user, ActivityLog $log): bool
    {
        return $this->hasAccess($user, $log->company_id);
    }

    public function create(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function update(User $user, ActivityLog $log): bool
    {
        return $this->hasAccess($user, $log->company_id);
    }

    public function delete(User $user, ActivityLog $log): bool
    {
        return $this->hasAccess($user, $log->company_id);
    }

    private function hasAccess(User $user, $companyId = null): bool
    {
        // Super Admin
        if ($user->is_super_admin) {
            if (Tenant::hasTenant()) {
                return $companyId == Tenant::id();
            }
            return true;
        }

        // User عادي
        return $companyId == $user->company_id;
    }
}
