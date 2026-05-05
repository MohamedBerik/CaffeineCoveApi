<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppointmentPolicy
{
    use HandlesAuthorization;

    // ✅ أضف viewAny
    public function viewAny(User $user): bool
    {
        // Super Admin يعدي
        if ($user->is_super_admin) {
            return true;
        }

        // ✅ أي مستخدم مسجل دخول وله صلاحية 'appointments.view' أو 'appointments.manage' يعدي
        return $user->hasPermissionTo('appointments.view') || $user->hasPermissionTo('appointments.manage');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        // نفس الشركة
        if ($appointment->company_id !== $user->company_id) {
            return false;
        }

        // ✅ شرط الفرع
        if (!is_null($user->branch_id)) {
            return $appointment->branch_id === $user->branch_id;
        }

        return true;
    }

    public function update(User $user, Appointment $appointment): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        // نفس الشركة
        if ($appointment->company_id !== $user->company_id) {
            return false;
        }

        // ✅ شرط الفرع
        if (!is_null($user->branch_id)) {
            return $appointment->branch_id === $user->branch_id;
        }

        return true;
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        // نفس الشركة
        if ($appointment->company_id !== $user->company_id) {
            return false;
        }

        // ✅ شرط الفرع
        if (!is_null($user->branch_id)) {
            return $appointment->branch_id === $user->branch_id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || !is_null($user->company_id);
    }
}
