<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Services\Tenant;
use Illuminate\Support\Facades\Schema;

class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        // لو مفيش Branch Context، خروج من غير فلترة
        if (!Tenant::branchId()) {
            return;
        }

        // ✅ التحقق من وجود عمود branch_id في جدول الـ Model
        if (!Schema::hasColumn($model->getTable(), 'branch_id')) {
            return; // تجاهل الـ Model ده
        }

        // تطبيق الفلترة
        $builder->where($model->getTable() . '.branch_id', Tenant::branchId());
    }
}
