<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Tenant;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->hasAccess($user, $model->company_id);
    }

    public function create(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function update(User $user, User $model): bool
    {
        // منع تعديل Super Admin بواسطة Company Admin
        if ($model->is_super_admin && !$user->is_super_admin) {
            return false;
        }

        return $this->hasAccess($user, $model->company_id);
    }

    public function delete(User $user, User $model): bool
    {
        // منع حذف Super Admin
        if ($model->is_super_admin) {
            return false;
        }

        // منع المستخدم من حذف نفسه
        if ($user->id === $model->id) {
            return false;
        }

        return $this->hasAccess($user, $model->company_id);
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
