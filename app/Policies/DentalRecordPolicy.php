<?php

namespace App\Policies;

use App\Models\DentalRecord;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Auth\Access\HandlesAuthorization;

class DentalRecordPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('dental_records.view');
    }

    public function view(User $user, DentalRecord $record): bool
    {
        if (
            $user->hasPermissionTo('dental_records.view') ||
            $user->hasPermissionTo('dental_records.create')
        ) {
            return $record->company_id == $user->company_id;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('dental_records.create');
    }

    public function update(User $user, DentalRecord $record): bool
    {
        return $user->hasPermissionTo('dental_records.view')
            && $record->company_id == $user->company_id;
    }

    public function delete(User $user, DentalRecord $record): bool
    {
        return $user->hasPermissionTo('dental_records.view')
            && $record->company_id == $user->company_id;
    }
}
