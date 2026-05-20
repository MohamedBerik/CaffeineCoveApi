<?php

namespace App\Models\Concerns;

use App\Services\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $branchId = Tenant::branchId();

        if (!$branchId) {
            return;
        }

        // IMPORTANT:
        // Apply only if model explicitly supports branching
        if (!property_exists($model, 'hasBranchScope') || !$model->hasBranchScope) {
            return;
        }

        $builder->where(
            $model->qualifyColumn('branch_id'),
            $branchId
        );
    }
}
