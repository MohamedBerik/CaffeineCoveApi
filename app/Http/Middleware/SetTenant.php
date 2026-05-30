<?php

namespace App\Http\Middleware;

use App\Exceptions\TenantException;
use Closure;
use Illuminate\Http\Request;
use App\Services\Tenant;
use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SetTenant
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // 1. تخطي مسارات تسجيل الدخول والتسجيل
            if ($request->is('api/login') || $request->is('api/register')) {
                Tenant::setId(null);
                Tenant::setIsSuperAdmin(false);
                return $next($request);
            }

            $tenantIdFromHeader = $request->header('X-Tenant-ID');
            $user = $request->user();

            if (!$user) {
                Tenant::setId(null);
                Tenant::setIsSuperAdmin(false);
                return $next($request);
            }

            $isGlobalMode = !$tenantIdFromHeader || $tenantIdFromHeader === 'global';

            // Super Admin يحتاج إلى tenant في ERP
            if ($user->is_super_admin && $this->isErpRoute($request)) {
                if ($isGlobalMode) {
                    return response()->json([
                        'message' => 'Tenant ID is required for ERP operations. Please select a clinic.',
                    ], 400);
                }
            }

            // Super Admin Logic
            if ($user->is_super_admin) {
                Tenant::setIsSuperAdmin(true);

                if ($isGlobalMode) {
                    Tenant::setId(null);
                    return $next($request);
                }

                $company = $this->findCompany($tenantIdFromHeader);

                if (!$company) {
                    return response()->json(['message' => 'Invalid company ID'], 400);
                }

                Tenant::setId($company->id);
                return $next($request);
            }

            // Regular User Logic
            if ($user->company_id) {
                $company = $this->findCompany($user->company_id);

                if (!$company) {
                    return response()->json([
                        'message' => 'Your company account could not be found. Please contact support.',
                        'code' => 'TENANT_NOT_FOUND'
                    ], 403);
                }

                // الفحص الموحد لحالة الشركة
                $blocked = in_array($company->status, ['suspended', 'cancelled']) ||
                    ($company->status === 'trial' && $company->trial_ends_at && now()->gt($company->trial_ends_at));

                if ($blocked) {
                    // السماح فقط بمسارات الفوترة
                    if ($request->is('api/erp/billing*')) {
                        Tenant::setId($user->company_id);
                        Tenant::setIsSuperAdmin(false);
                        return $next($request);
                    }

                    // رفض مع رسالة واضحة
                    $message = $company->status === 'suspended'
                        ? 'Your clinic account has been suspended. Please subscribe to reactivate.'
                        : 'Your free trial has ended. Please subscribe to continue using the system.';

                    return response()->json([
                        'message' => $message,
                        'code' => 'TENANT_' . strtoupper($company->status)
                    ], 403);
                }

                Tenant::setId($user->company_id);
                Tenant::setIsSuperAdmin(false);
                return $next($request);
            }

            // User بدون Company
            return response()->json([
                'message' => 'Your account is not associated with any clinic. Please contact support.',
                'code' => 'TENANT_NOT_FOUND'
            ], 403);
        } catch (\Throwable $e) {
            // 🎯 صيد أي كسر مفاجئ وإرجاعه للمتصفح لمنع الـ 500 الصامتة
            return response()->json([
                'message' => 'Internal server error during tenant resolution',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    private function isErpRoute(Request $request): bool
    {
        return str_contains($request->path(), 'erp/') || $request->is('api/erp/*');
    }

    private function findCompany($companyId): ?Company
    {
        if (!$companyId) return null;
        $cacheKey = "company_{$companyId}_basic";
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($companyId) {
            return Company::select('id', 'name', 'slug', 'status', 'trial_ends_at')->find($companyId);
        });
    }
}
