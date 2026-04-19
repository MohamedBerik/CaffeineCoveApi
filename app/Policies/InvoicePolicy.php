<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->hasAccess($user, $invoice->company_id);
    }

    public function create(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->hasAccess($user, $invoice->company_id);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->hasAccess($user, $invoice->company_id);
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
