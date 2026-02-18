<?php

namespace App\Models\Concerns;

use App\Models\Concerns\CompanyScope;
use Illuminate\Support\Facades\Schema;

trait BelongsToCompanyTrait
{
    protected static function bootBelongsToCompanyTrait()
    {
        /*
         | Global scope (company filter)
         | super admin يجب أن يتجاوز هذا الفلتر
         */
        static::addGlobalScope(new CompanyScope);

        /*
         | Auto assign company_id on create
         */
        static::creating(function ($model) {

            // لا يوجد auth (artisan, seeder, job...)
            if (!auth()->check()) {
                return;
            }

            $user = auth()->user();

            // super admin لا نربطه تلقائيًا بشركة
            if ($user->isSuperAdmin()) {
                return;
            }

            // لو الموديل لا يحتوي company_id
            if (!Schema::hasColumn($model->getTable(), 'company_id')) {
                return;
            }

            // لو تم تحديد الشركة يدويًا لا نغيرها
            if (!empty($model->company_id)) {
                return;
            }

            if ($user->company_id) {
                $model->company_id = $user->company_id;
            }
        });
    }
}
