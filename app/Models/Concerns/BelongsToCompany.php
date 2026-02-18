<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        // لو مفيش مستخدم (CLI / seeder / job)
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();

        // super admin يرى كل الشركات
        if ($user->is_super_admin) {
            return;
        }

        if ($user->company_id) {
            $builder->where(
                $model->getTable() . '.company_id',
                $user->company_id
            );
        }
    }
}
