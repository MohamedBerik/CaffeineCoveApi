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
        return true; // سيتم الفلترة عبر BranchScope
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('patients.view')
            && $this->hasCompanyAccess($user, $customer->company_id)
            && $this->hasBranchAccess($user, $customer->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('patients.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('patients.manage')
            && $this->hasCompanyAccess($user, $customer->company_id)
            && $this->hasBranchAccess($user, $customer->branch_id);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('patients.manage')
            && $this->hasCompanyAccess($user, $customer->company_id)
            && $this->hasBranchAccess($user, $customer->branch_id);
    }

    /**
     * التحقق من أن المستخدم ينتمي لنفس الشركة.
     */
    private function hasCompanyAccess(User $user, $companyId): bool
    {
        // Super Admin في وضع Global يرى الكل
        if ($user->is_super_admin && !Tenant::hasTenant()) {
            return true;
        }
        // غير ذلك، يجب أن يتطابق company_id
        return $companyId == $user->company_id;
    }

    /**
     * التحقق من عزل الفروع.
     */
    private function hasBranchAccess(User $user, $branchId): bool
    {
        // Super Admin يرى الكل
        if ($user->is_super_admin) {
            return true;
        }
        // إذا لم يكن للمستخدم فرع (مدير شركة)، يرى الكل
        if ($user->branch_id === null) {
            return true;
        }
        // إذا لم يكن للسجل فرع (بيانات قديمة)، اسمح (أو يمكنك رفض حسب الحاجة)
        if ($branchId === null) {
            return true;
        }
        // يجب أن يتطابق فرع المستخدم مع فرع المريض
        return $branchId == $user->branch_id;
    }
}
