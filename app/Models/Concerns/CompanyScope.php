<?php
// app/Models/Concerns/CompanyScope.php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Services\Tenant;

class CompanyScope implements Scope
{

    public function apply(Builder $builder, Model $model)
    {
        $companyId = Tenant::id();
        $isSuperAdmin = Tenant::isSuperAdmin();

        // ✅ Super Admin بدون شركة يشوف كل حاجة
        if ($isSuperAdmin && !Tenant::hasTenant()) {
            return;
        }

        if (!property_exists(get_class($model), 'hasCompanyColumn') || !$model::$hasCompanyColumn) {
            return;
        }

        // ✅ إصلاح: لو مفيش company_id، نرجع بدون فلتر (بدل WHERE 1=0)
        if (!$companyId) {
            return;
        }

        $builder->where($model->getTable() . '.company_id', $companyId);
    }
}
