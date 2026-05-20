<?php

namespace App\Models\Concerns;

use App\Services\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BranchScope implements Scope
{
    protected array $branchAwareTables = [
        'appointments',
        'customers',
        'payments',
        'invoices',
        'treatment_plans',
        'dental_records',
        'activity_logs',
        'billing_invoices',
        'categories',
        'clinic_settings',
        'customer_credits',
        'customer_ledger_entries',
        'doctors',
        'employees',
        '',
    ];

    public function apply(Builder $builder, Model $model)
    {
        $branchId = app()->has('tenant_branch_id')
            ? app('tenant_branch_id')
            : Tenant::branchId();

        if (!$branchId) {
            return;
        }

        $table = $model->getTable();

        if (!in_array($table, $this->branchAwareTables)) {
            return;
        }

        $builder->where($table . '.branch_id', $branchId);
    }
}
