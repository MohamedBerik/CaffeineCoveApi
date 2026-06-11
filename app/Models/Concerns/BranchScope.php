<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Services\Tenant;

class BranchScope implements Scope
{
    /**
     * قائمة الجداول المعتمدة على الفروع (Whitelist)
     * تمنع استخدام Schema::hasColumn المبطئ للعمليات
     */
    protected array $branchAwareTables = [
        'appointments',
        'customers',
        'payments',
        'invoices',
        'treatment_plans',
        'dental_records',
        'users',
        'radiologies',
        'system_alerts',
    ];

    public function apply(Builder $builder, Model $model)
    {
        \Log::info('BRANCH_SCOPE_DEBUG', [
            'model' => get_class($model),
            'table' => $model->getTable(),
            'tenant_branch' => Tenant::branchId(),
            'container_branch' => app()->has('tenant_branch_id')
                ? app('tenant_branch_id')
                : null,
        ]);

        $branchId = app()->has('tenant_branch_id')
            ? app('tenant_branch_id')
            : Tenant::branchId();

        \Log::info('BRANCH_SCOPE_AFTER_RESOLVE', [
            'branch_id' => $branchId,
        ]);

        if (!$branchId) {
            return;
        }

        $table = $model->getTable();

        \Log::info('BRANCH_SCOPE_TABLE_CHECK', [
            'table' => $table,
            'exists' => in_array($table, $this->branchAwareTables),
        ]);

        if (!in_array($table, $this->branchAwareTables)) {
            return;
        }

        \Log::info('BRANCH_SCOPE_APPLYING_WHERE', [
            'table' => $table,
            'branch_id' => $branchId,
        ]);

        $builder->where($table . '.branch_id', $branchId);
    }
}
