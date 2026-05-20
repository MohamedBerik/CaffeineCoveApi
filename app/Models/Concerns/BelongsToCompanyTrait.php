<?php

namespace App\Models\Concerns;

use App\Models\Concerns\CompanyScope;
use App\Services\Tenant;
use App\Models\Concerns\BranchScope;

trait BelongsToCompanyTrait
{
    /**
     * ✅ Performance fix
     */
    public static $hasCompanyColumn = true;

    /** @var bool */

    public static $hasBranchColumn = false; // ✅ علامة جديدة

    protected static function bootBelongsToCompanyTrait()
    {
        static::addGlobalScope(new CompanyScope);

        // ✅ أضف BranchScope دائمًا - سيقرر بنفسه متى يُفلتر
        static::addGlobalScope(new BranchScope);

        static::creating(function ($model) {
            // ✅ لو الـ Model مش محتاج company_id
            if (!static::$hasCompanyColumn) {
                return;
            }

            $companyId = Tenant::id();
            $isSuperAdmin = Tenant::isSuperAdmin();

            // ✅ Super Admin بدون Tenant Context
            if ($isSuperAdmin && !Tenant::hasTenant()) {
                // لو الـ company_id موجود، خلاص
                if (!empty($model->company_id)) {
                    return;
                }
                // السماح بإنشاء السجل بدون company_id (لـ Super Admin فقط)
                return;
            }

            // ✅ لو company_id موجود بالفعل، خلاص
            if (!empty($model->company_id)) {
                return;
            }

            // ✅ لو companyId مش موجود والمستخدم مش Super Admin → خطأ
            if (!$companyId) {
                throw new \Exception('Tenant not resolved for model: ' . get_class($model));
            }

            // ✅ تعيين company_id تلقائيًا
            $model->company_id = $companyId;

            // ✅ إضافة branch_id تلقائياً (لو موجود في السياق)
            $resolvedBranchId = app()->has('tenant_branch_id') ? app('tenant_branch_id') : Tenant::branchId();
            if (empty($model->branch_id) && $resolvedBranchId) {
                $model->branch_id = $resolvedBranchId;
            }
        });

        // ✅ منع تغيير company_id بعد الإنشاء
        static::updating(function ($model) {
            if (!static::$hasCompanyColumn) {
                return;
            }

            // ✅ لو مش Super Admin والـ company_id اتغير → منع
            if (!Tenant::isSuperAdmin() && $model->isDirty('company_id')) {
                throw new \Exception('Cannot change company_id');
            }
        });
    }

    /**
     * ✅ إزالة BranchScope (لـ Super Admin فقط)
     */
    public static function withoutBranchScope()
    {
        if (!Tenant::isSuperAdmin()) {
            throw new \Exception('Only super admin can remove branch scope');
        }

        return static::withoutGlobalScope(BranchScope::class);
    }
    /**
     * ✅ الاستعلام عن كل الشركات (لـ Super Admin فقط)
     */
    public static function allCompanies()
    {
        if (!Tenant::isSuperAdmin()) {
            throw new \Exception('Only super admin can query all companies');
        }

        return static::withoutGlobalScope(CompanyScope::class);
    }

    /**
     * ✅ العلاقة مع الشركة
     */
    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }
    /**
     * ✅ Scope: فلترة حسب الشركة الحالية
     */
    public function scopeForCurrentCompany($query)
    {
        $companyId = Tenant::id();

        if (!$companyId) {
            return $query;
        }

        return $query->where('company_id', $companyId);
    }

    /**
     * ✅ التحقق إذا كان السجل تبع الشركة الحالية
     */
    public function belongsToCurrentCompany(): bool
    {
        $companyId = Tenant::id();

        if (!$companyId) {
            return false;
        }

        return $this->company_id == $companyId;
    }
}
