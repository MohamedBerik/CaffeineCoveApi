<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->hasAccess($user, $customer->company_id);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->hasAccess($user, $customer->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('patients.manage');
    }



    public function delete(User $user, Customer $customer): bool
    {
        return $this->hasAccess($user, $customer->company_id);
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
