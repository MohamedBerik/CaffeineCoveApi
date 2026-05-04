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
        $hasPerm = $user->hasPermissionTo('doctors.view');
        $companyOk = $this->hasCompanyAccess($user, $doctor->company_id);
        $branchOk = $this->hasBranchAccess($user, $doctor->branch_id);

        \Log::info('DoctorPolicy view check', [
            'user_id' => $user->id,
            'user_branch' => $user->branch_id,
            'doctor_id' => $doctor->id,
            'doctor_branch' => $doctor->branch_id,
            'dr_company' => $doctor->company_id,
            'hasPerm' => $hasPerm,
            'companyOk' => $companyOk,
            'branchOk' => $branchOk,
        ]);

        return $hasPerm && $companyOk && $branchOk;
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
        // Super Admin يرى الكل
        if ($user->is_super_admin) {
            return true;
        }
        // مدير الشركة (بدون فرع) يرى الكل
        if ($user->branch_id === null) {
            return true;
        }
        // بيانات قديمة بدون فرع – اسمح بها (يمكن تغييرها إذا أردت منعها)
        if ($branchId === null) {
            return true;
        }
        // يجب أن يتطابق فرع المستخدم مع فرع الطبيب
        return $branchId == $user->branch_id;
    }
}
