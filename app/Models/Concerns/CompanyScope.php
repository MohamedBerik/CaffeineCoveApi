<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Services\Tenant;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        // لا تطبق على super admin global context
        if (Tenant::isSuperAdmin() && !Tenant::hasTenant()) {
            return;
        }

        // تجاهل الموديلات التي لا تحتوي company_id
        if (
            !property_exists($model, 'hasCompanyColumn') ||
            !$model::$hasCompanyColumn
        ) {
            return;
        }

        $companyId = Tenant::id();

        // fail safe بدون exceptions
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
