<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Services\Tenant;

class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $branchId = Tenant::branchId();

        // ✅ لو مفيش Branch Context (مدير شركة أو Super Admin في Global Mode) - ما ترجعش بيانات
        if (!$branchId) {
            return;
        }

        // ✅ لو الـ Model مش عنده branch_id (زي Company أو Plan) - تجاهل الفلترة
        if (!property_exists(get_class($model), 'hasBranchColumn') || !$model::$hasBranchColumn) {
            return;
        }

        // ✅ تطبيق الفلترة
        $builder->where($model->getTable() . '.branch_id', $branchId);
    }
}
