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
        // إذا لم يتم تحديد فرع (أي في وضع المدير العام)، لا تُطبق أي فلترة
        if (!Tenant::branchId()) {
            return;
        }

        // بخلاف ذلك، فلترة بالـ branch_id الحالي
        $builder->where($model->getTable() . '.branch_id', Tenant::branchId());
    }
}
