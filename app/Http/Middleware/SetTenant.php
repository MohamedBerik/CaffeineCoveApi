<?php

namespace App\Http\Middleware;

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
        $user = $request->user();

        if (!$user) {
            // ✅ Public Routes - No Tenant Context
            Tenant::setId(null);
            Tenant::setIsSuperAdmin(false);

            Log::info('Tenant Check - Public Route', [
                'path' => $request->path(),
                'tenant_id' => null,
            ]);

            return $next($request);
        }

        // ✅ Super Admin Logic
        if ($user->is_super_admin) {
            Tenant::setIsSuperAdmin(true);

            $tenantIdFromHeader = $request->header('X-Tenant-ID');

            // ✅ لو عايز Global Mode (بدون شركة)
            if ($tenantIdFromHeader === null || $tenantIdFromHeader === 'null' || $tenantIdFromHeader === '') {
                Tenant::setId(null);

                Log::info('Tenant Check - Super Admin (Global Mode)', [
                    'user_id' => $user->id,
                    'tenant_id' => null,
                ]);

                return $next($request);
            }

            // ✅ التحقق من وجود الشركة وصلاحيتها
            $company = $this->findCompany($tenantIdFromHeader);

            if (!$company) {
                Log::warning('Tenant Check - Invalid Company ID', [
                    'user_id' => $user->id,
                    'requested_company_id' => $tenantIdFromHeader,
                ]);

                return response()->json([
                    'message' => 'Invalid company ID',
                    'requested_id' => $tenantIdFromHeader,
                ], 400);
            }

            if ($company->status === 'suspended') {
                Log::warning('Tenant Check - Suspended Company', [
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'company_status' => $company->status,
                ]);

                // ✅ اختياري: نسمح لـ Super Admin بالدخول للشركات المعلقة
                // return response()->json(['message' => 'Company is suspended'], 403);
            }

            Tenant::setId($company->id);

            Log::info('Tenant Check - Super Admin (Company Mode)', [
                'user_id' => $user->id,
                'tenant_id' => $company->id,
                'company_slug' => $company->slug,
                'company_status' => $company->status,
            ]);

            return $next($request);
        }

        // ✅ Regular User Logic
        if ($user->company_id) {
            // ✅ Cache الشركة عشان نقلل الـ Queries
            $company = $this->findCompany($user->company_id);

            if (!$company) {
                Log::error('Tenant Check - User Company Not Found', [
                    'user_id' => $user->id,
                    'company_id' => $user->company_id,
                ]);

                Tenant::setId(null);
                Tenant::setIsSuperAdmin(false);

                return response()->json([
                    'message' => 'Your company account could not be found. Please contact support.',
                ], 403);
            }

            if ($company->status === 'suspended') {
                Log::warning('Tenant Check - Suspended Company Access Attempt', [
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                ]);

                Tenant::setId(null);
                Tenant::setIsSuperAdmin(false);

                return response()->json([
                    'message' => 'Your clinic account has been suspended. Please contact support.',
                ], 403);
            }

            Tenant::setId($user->company_id);
            Tenant::setIsSuperAdmin(false);

            Log::info('Tenant Check - Regular User', [
                'user_id' => $user->id,
                'tenant_id' => $user->company_id,
                'company_slug' => $company->slug,
            ]);

            return $next($request);
        }

        // ❌ User بدون Company (حالة خطأ)
        Log::error('Tenant Check - User Without Company', [
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);

        Tenant::setId(null);
        Tenant::setIsSuperAdmin(false);

        return response()->json([
            'message' => 'Your account is not associated with any clinic. Please contact support.',
        ], 403);
    }

    /**
     * ✅ العثور على الشركة مع Cache لتقليل الـ Database Queries
     */
    private function findCompany($companyId): ?Company
    {
        if (!$companyId) {
            return null;
        }

        $cacheKey = "company_{$companyId}_basic";

        return Cache::remember($cacheKey, 3600, function () use ($companyId) {
            return Company::select('id', 'name', 'slug', 'status')->find($companyId);
        });
    }
}
