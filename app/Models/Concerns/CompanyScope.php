<?php
// app/Models/Concerns/CompanyScope.php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Services\Tenant;

class CompanyScope implements Scope
{

    // app/Models/Concerns/CompanyScope.php

    public function apply(Builder $builder, Model $model)
    {
        $companyId = Tenant::id();
        $isSuperAdmin = Tenant::isSuperAdmin();

        // Super admin يرى كل الشركات
        if ($isSuperAdmin && !Tenant::hasTenant()) {
            return;
        }

        // ✅ Performance fix
        if (!property_exists($model, 'hasCompanyColumn') || !$model::$hasCompanyColumn) {
            return;
        }

        // 🔥 PATCH: fallback بدل empty
        if (!$companyId) {
            $companyId = 1;
        }

        $builder->where(
            $model->getTable() . '.company_id',
            $companyId
        );
    }
}


//for testing
    // public function apply(Builder $builder, Model $model)
    // {
    //     $companyId = Tenant::id();
    //     $isSuperAdmin = Tenant::isSuperAdmin();

    //     if ($isSuperAdmin && !Tenant::hasTenant()) {
    //         return;
    //     }

    //     // ✅ حل مؤقت: تجاهل hasCompanyColumn لو الموديل هو User
    //     if ($model instanceof \App\Models\User) {
    //         // متعملش حاجة
    //     } else {
    //         if (!property_exists(get_class($model), 'hasCompanyColumn') || !$model::$hasCompanyColumn) {
    //             return;
    //         }
    //     }

    //     if (!$companyId) {
    //         $companyId = 1;
    //     }

    //     $builder->where($model->getTable() . '.company_id', $companyId);
    // }
