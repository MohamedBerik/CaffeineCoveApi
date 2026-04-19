<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->hasAccess($user, $supplier->company_id);
    }

    public function create(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->hasAccess($user, $supplier->company_id);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->hasAccess($user, $supplier->company_id);
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

        // Regular user
        return $companyId == $user->company_id;
    }
}
