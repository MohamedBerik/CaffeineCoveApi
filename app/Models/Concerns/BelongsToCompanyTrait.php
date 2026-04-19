<?php
// app/Models/Concerns/BelongsToCompanyTrait.php

namespace App\Models\Concerns;

use App\Models\Concerns\CompanyScope;
use App\Services\Tenant;

trait BelongsToCompanyTrait
{
    /**
     * ✅ Performance fix
     */
    public static $hasCompanyColumn = true;

    protected static function bootBelongsToCompanyTrait()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $companyId = Tenant::id();
            $isSuperAdmin = Tenant::isSuperAdmin();

            // ✅ إصلاح: منع الـ Exception لو Super Admin بدون شركة
            if ($isSuperAdmin && !Tenant::hasTenant()) {
                // ✅ بدل ما نرمي Exception، نستخدم company_id من الـ Model لو موجود
                if (empty($model->company_id)) {
                    // ✅ السماح بإنشاء السجل بدون company_id (لـ Super Admin فقط)
                    return;
                }
                return;
            }

            if (!static::$hasCompanyColumn) {
                return;
            }

            if ($companyId && empty($model->company_id)) {
                $model->company_id = $companyId;
            }
        });
    }

    /**
     * ✅ إصلاح المشكلة 2: withoutCompanyScope للـ Super Admin فقط
     */
    public static function withoutCompanyScope()
    {
        if (!Tenant::isSuperAdmin()) {
            throw new \Exception('Only super admin can remove company scope');
        }

        return static::withoutGlobalScope(CompanyScope::class);
    }

    /**
     * ✅ إصلاح المشكلة 2: allCompanies للـ Super Admin فقط
     */
    public static function allCompanies()
    {
        if (!Tenant::isSuperAdmin()) {
            throw new \Exception('Only super admin can query all companies');
        }

        return static::withoutGlobalScope(CompanyScope::class);
    }

    /*
     | العلاقة مع الشركة
     */
    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    /*
     | Scope: فلترة حسب الشركة الحالية
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
