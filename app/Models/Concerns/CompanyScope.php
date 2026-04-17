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

    // app/Models/Concerns/CompanyScope.php

    public function apply(Builder $builder, Model $model)
    {
        $companyId = Tenant::id();
        $isSuperAdmin = Tenant::isSuperAdmin();

        if ($isSuperAdmin && !Tenant::hasTenant()) {
            return;
        }

        if (!property_exists(get_class($model), 'hasCompanyColumn') || !$model::$hasCompanyColumn) {
            return;
        }

        // ✅ [إصلاح] إذا لم يكن هناك company_id، نخرج بدون إضافة أي فلتر
        if (!$companyId) {
            return; // ❌ احذف السطر $builder->whereRaw('1 = 0');
        }

        $builder->where($model->getTable() . '.company_id', $companyId);
    }
}
