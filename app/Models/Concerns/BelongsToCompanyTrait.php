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
    protected static $hasCompanyColumn = true;
    //دالة مؤقته وتتحذف
    public function hasCompanyColumnCheck(): bool
    {
        return static::$hasCompanyColumn;
    }

    protected static function bootBelongsToCompanyTrait()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $companyId = Tenant::id();
            $isSuperAdmin = Tenant::isSuperAdmin();

            // ✅ إصلاح المشكلة 1: منع override غير مصرح به
            // Super admin ممنوع يدخل company_id إلا لو explicitly using Tenant::forCompany()
            if ($isSuperAdmin && !Tenant::hasTenant()) {
                // لو Super Admin وعايز ينشئ حاجة - لازم يحدد الشركة explicitly
                if (empty($model->company_id)) {
                    throw new \Exception('Super admin must explicitly set company_id when creating records');
                }
                return; // ✅ استخدام company_id اللي هو حطه manually
            }

            // ✅ Performance fix
            if (!static::$hasCompanyColumn) {
                return;
            }

            // ✅ إصلاح المشكلة 1: منع override - نستخدم company_id من Tenant فقط
            // لو المستخدم مش Super Admin - دايمًا نستخدم company_id بتاعه
            if ($companyId) {
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
