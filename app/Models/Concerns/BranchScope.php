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
        'radiologies', // أضف أي جداول أخرى عيادية تملك عمود branch_id هنا
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

        // فحص سريع جداً في الذاكرة دون لمس DB
        if (!in_array($table, $this->branchAwareTables)) {
            return;
        }

        $builder->where($table . '.branch_id', $branchId);
    }
}
