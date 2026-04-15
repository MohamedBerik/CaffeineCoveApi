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
        // ✅ استخدام Tenant بدل Auth (يدعم CLI/Jobs)
        $companyId = Tenant::id();
        $isSuperAdmin = Tenant::isSuperAdmin();

        // Super admin يرى كل الشركات
        if ($isSuperAdmin) {
            return;
        }

        // ✅ Performance fix: التحقق من وجود العمود عن طريق property
        if (!property_exists($model, 'hasCompanyColumn') || !$model::$hasCompanyColumn) {
            return;
        }

        // مستخدم بدون شركة - يرجع فاضي
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
