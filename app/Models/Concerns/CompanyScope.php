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

        // ✅ Super Admin بدون شركة = يشوف كل حاجة
        if ($isSuperAdmin && !Tenant::hasTenant()) {
            return;
        }

        // ✅ تجاهل User Model عشان Sanctum
        if ($model instanceof \App\Models\User) {
            return;
        }

        // ✅ التحقق من وجود hasCompanyColumn
        if (!property_exists(get_class($model), 'hasCompanyColumn') || !$model::$hasCompanyColumn) {
            return;
        }

        // ✅ أمان: لو مفيش company_id، ارجع فاضي (مافيش تسريب بيانات)
        if (!$companyId) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where($model->getTable() . '.company_id', $companyId);
    }
}
