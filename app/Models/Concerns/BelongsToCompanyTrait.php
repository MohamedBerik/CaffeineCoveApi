<?php

namespace App\Models\Concerns;

use App\Models\Concerns\CompanyScope;

trait BelongsToCompanyTrait
{
    protected static function bootBelongsToCompanyTrait()
    {
        static::addGlobalScope(new CompanyScope);

        // في حالة الإنشاء التلقائي
        static::creating(function ($model) {

            if (
                auth()->check()
                && auth()->user()->company_id
                && empty($model->company_id)
            ) {
                $model->company_id = auth()->user()->company_id;
            }
        });
    }
}
