<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        // في حالات console / seeder / job
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();

        // super admin يرى كل الشركات
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return;
        }

        // لو الجدول لا يحتوي company_id أصلاً
        if (!Schema::hasColumn($model->getTable(), 'company_id')) {
            return;
        }

        // مستخدم بدون شركة (غير مسموح له أصلاً في API)
        if (!$user->company_id) {
            // نخلي الاستعلام يرجع فاضي بدل ما يرجّع كل الداتا
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where(
            $model->getTable() . '.company_id',
            $user->company_id
        );
    }
}
