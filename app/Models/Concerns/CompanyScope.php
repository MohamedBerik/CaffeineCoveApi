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

        // ✅ [إصلاح] لا نلغي الفلترة إلا إذا كان Super Admin خارج سياق أي شركة
        if ($isSuperAdmin && !Tenant::hasTenant()) {
            return;
        }

        // ✅ [إصلاح] التحقق من وجود الخاصية بشكل صحيح
        if (!property_exists(get_class($model), 'hasCompanyColumn') || !$model::$hasCompanyColumn) {
            return;
        }

        if (!$companyId) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where(
            $model->getTable() . '.company_id',
            $companyId
        );
    }
}
