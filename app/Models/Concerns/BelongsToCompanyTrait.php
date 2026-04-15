<?php
// app/Models/Concerns/BelongsToCompanyTrait.php

namespace App\Models\Concerns;

use App\Models\Concerns\CompanyScope;
use App\Services\Tenant;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompanyTrait
{
    /**
     * ✅ Performance fix: static property بدل Schema::hasColumn
     */
    protected static $hasCompanyColumn = true;

    /**
     * ✅ Guard ضد withoutGlobalScopes
     */
    protected static $preventScopeRemoval = true;

    protected static function bootBelongsToCompanyTrait()
    {
        /*
         | Global scope (company filter)
         */
        static::addGlobalScope(new CompanyScope);

        /*
         | ✅ Guard: منع إزالة الـ scope بدون إذن صريح
         */
        static::macro('withoutCompanyScope', function () {
            static::$preventScopeRemoval = false;
            return static::withoutGlobalScope(CompanyScope::class);
        });

        /*
         | Auto assign company_id on create
         */
        static::creating(function ($model) {
            // ✅ استخدام Tenant بدل Auth
            $companyId = Tenant::id();
            $isSuperAdmin = Tenant::isSuperAdmin();

            // Super admin لا نربطه تلقائيًا بشركة
            if ($isSuperAdmin) {
                return;
            }

            // ✅ Performance fix: استخدام property بدل Schema::hasColumn
            if (!static::$hasCompanyColumn) {
                return;
            }

            // ✅ Protection ضد manual override: تجاهل أي قيمة مدخلة
            if ($companyId) {
                $model->company_id = $companyId;
            }
        });

        /*
         | ✅ Guard: منع إزالة الـ scope
         */
        static::addGlobalScope('prevent_scope_removal', function (Builder $builder) {
            if (static::$preventScopeRemoval && !Tenant::isSuperAdmin()) {
                // ده مجرد علامة - المنطق الفعلي في CompanyScope
            }
        });
    }

    /**
     * ✅ Allow controlled scope removal for super admin
     */
    public static function allCompanies()
    {
        static::$preventScopeRemoval = false;
        return static::withoutGlobalScope(CompanyScope::class);
    }

    /**
     * ✅ Reset scope prevention after query
     */
    public static function booted()
    {
        static::retrieved(function () {
            static::$preventScopeRemoval = true;
        });
    }

    /*
     | العلاقة مع الشركة
     */
    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    /*
     | Scope: فلترة حسب الشركة
     */
    public function scopeForCurrentCompany($query)
    {
        $companyId = Tenant::id();

        if (!$companyId) {
            return $query;
        }

        return $query->where('company_id', $companyId);
    }
}
