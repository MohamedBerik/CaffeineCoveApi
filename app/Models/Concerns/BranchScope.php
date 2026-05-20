<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Services\Tenant;
use Illuminate\Support\Facades\Schema;

class BranchScope implements Scope
{
    protected static $hasBranchColumnCache = [];

    public function apply(Builder $builder, Model $model)
    {
        $branchId = app()->has('tenant_branch_id')
            ? app('tenant_branch_id')
            : Tenant::branchId();

        if (!$branchId) {
            return;
        }

        $table = $model->getTable();

        if (!isset(self::$hasBranchColumnCache[$table])) {
            self::$hasBranchColumnCache[$table] = Schema::hasColumn($table, 'branch_id');
        }

        if (!self::$hasBranchColumnCache[$table]) {
            return;
        }

        $builder->where($model->getTable() . '.branch_id', $branchId);
    }
}
