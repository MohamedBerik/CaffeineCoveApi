<?php

namespace App\Policies;

use App\Models\SystemAlert;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Auth\Access\HandlesAuthorization;

class SystemAlertPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function view(User $user, SystemAlert $alert): bool
    {
        return $this->hasAccess($user, $alert->company_id);
    }

    public function create(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function update(User $user, SystemAlert $alert): bool
    {
        return $this->hasAccess($user, $alert->company_id);
    }

    public function delete(User $user, SystemAlert $alert): bool
    {
        return $this->hasAccess($user, $alert->company_id);
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
