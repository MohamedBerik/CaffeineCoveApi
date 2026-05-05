<?php

namespace App\Policies;

use App\Models\Doctor;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Auth\Access\HandlesAuthorization;

class DoctorPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('doctors.view');
    }

    public function view(User $user, Doctor $doctor): bool
    {
        return $user->hasPermissionTo('doctors.view')
            && $this->hasCompanyAccess($user, $doctor->company_id)
            && $this->hasBranchAccess($user, $doctor->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('doctors.manage');
    }

    public function update(User $user, Doctor $doctor): bool
    {
        return $user->hasPermissionTo('doctors.manage')
            && $this->hasCompanyAccess($user, $doctor->company_id)
            && $this->hasBranchAccess($user, $doctor->branch_id);
    }

    public function delete(User $user, Doctor $doctor): bool
    {
        return $user->hasPermissionTo('doctors.manage')
            && $this->hasCompanyAccess($user, $doctor->company_id)
            && $this->hasBranchAccess($user, $doctor->branch_id);
    }

    private function hasCompanyAccess(User $user, $companyId): bool
    {
        // Super Admin في وضع Global يرى الكل
        if ($user->is_super_admin && !Tenant::hasTenant()) {
            return true;
        }
        return $companyId == $user->company_id;
    }

    private function hasBranchAccess(User $user, $branchId): bool
    {
        if ($user->is_super_admin) {
            return true;
        }
        if ($user->branch_id === null) {
            return true; // مدير الشركة يرى الكل
        }
        // مستخدم عادي: يجب أن يتطابق فرع الطبيب مع فرع المستخدم
        return $branchId == $user->branch_id;
    }
}
