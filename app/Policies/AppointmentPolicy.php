<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppointmentPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->is_super_admin || $appointment->company_id === $user->company_id;
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->is_super_admin || $appointment->company_id === $user->company_id;
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->is_super_admin || $appointment->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || !is_null($user->company_id);
    }
}
