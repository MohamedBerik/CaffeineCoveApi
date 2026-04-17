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
        // ✅ تجاوز الـ Scope لـ Sanctum Guard (Token Authentication)
        if (request()->is('api/*') && auth()->guard('sanctum')->check() === false) {
            // لو المستخدم لسه موثوقش، متضفش الـ Scope
            return;
        }

        $companyId = Tenant::id();
        $isSuperAdmin = Tenant::isSuperAdmin();

        if ($isSuperAdmin) {
            return;
        }

        if (!$companyId) {
            // ✅ بدل ما نضيف WHERE 1=0، نرجع فاضي بدون Crash
            // $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where($model->getTable() . '.company_id', $companyId);
    }
}
