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

            // ✅ 1. Login/Register Bypass
            if ($request->is('api/login') || $request->is('api/register')) {
                Tenant::setId(null);
                Tenant::setIsSuperAdmin(false);
                return $next($request);
            }

            // ✅ تحسين #1: قراءة الـ Header مرة واحدة
            $tenantIdFromHeader = $request->header('X-Tenant-ID');

            $user = $request->user();

            if (!$user) {
                Tenant::setId(null);
                Tenant::setIsSuperAdmin(false);

                $this->logDebug('Tenant Check - Public Route', [
                    'path' => $request->path(),
                    'tenant_id' => null,
                ]);

                return $next($request);
            }

            // ✅ تحسين #2: تبسيط الشرط
            $isGlobalMode = !$tenantIdFromHeader || $tenantIdFromHeader === 'global';

            // ✅ Early return for ERP requirement
            if ($user->is_super_admin && $this->isErpRoute($request)) {
                if ($isGlobalMode) {
                    return response()->json([
                        'message' => 'Tenant ID is required for ERP operations. Please select a clinic.',
                    ], 400);
                }
            }

            // ✅ Super Admin Logic
            if ($user->is_super_admin) {
                Tenant::setIsSuperAdmin(true);

                if ($isGlobalMode) {
                    Tenant::setId(null);

                    $this->logDebug('Tenant Check - Super Admin (Global Mode)', [
                        'user_id' => $user->id,
                        'tenant_id' => null,
                    ]);

                    return $next($request);
                }

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

                if (!$user->canAccessCompany($company->id)) {
                    Log::warning('Tenant Check - Unauthorized Company Access', [
                        'user_id' => $user->id,
                        'company_id' => $company->id,
                    ]);

                    return response()->json([
                        'message' => 'Unauthorized company access',
                    ], 403);
                }

                if ($company->status === 'suspended') {
                    Log::warning('Tenant Check - Suspended Company', [
                        'user_id' => $user->id,
                        'company_id' => $company->id,
                        'company_status' => $company->status,
                    ]);
                }

                Tenant::setId($company->id);

                $this->logDebug('Tenant Check - Super Admin (Company Mode)', [
                    'user_id' => $user->id,
                    'tenant_id' => $company->id,
                    'company_slug' => $company->slug,
                    'company_status' => $company->status,
                ]);

                return $next($request);
            }

            // ✅ Regular User Logic
            if ($user->company_id) {
                $company = $this->findCompany($user->company_id);

                if (!$company) {
                    Log::error('Tenant Check - User Company Not Found', [
                        'user_id' => $user->id,
                        'company_id' => $user->company_id,
                    ]);

                    Tenant::setId(null);
                    Tenant::setIsSuperAdmin(false);

                    throw new TenantException(
                        'Your company account could not be found. Please contact support.',
                        403,
                        'TENANT_NOT_FOUND'
                    );
                }

                if ($company->status === 'suspended') {
                    Log::warning('Tenant Check - Suspended Company Access Attempt', [
                        'user_id' => $user->id,
                        'company_id' => $company->id,
                    ]);

                    Tenant::setId(null);
                    Tenant::setIsSuperAdmin(false);

                    throw new TenantException(
                        'Your clinic account has been suspended. Please contact support.',
                        403,
                        'TENANT_SUSPENDED'
                    );
                }

                Tenant::setId($user->company_id);
                Tenant::setIsSuperAdmin(false);

                $this->logDebug('Tenant Check - Regular User', [
                    'user_id' => $user->id,
                    'tenant_id' => $user->company_id,
                    'company_slug' => $company->slug,
                ]);

                return $next($request);
            }

            // ❌ User بدون Company
            Log::error('Tenant Check - User Without Company', [
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);

            Tenant::setId(null);
            Tenant::setIsSuperAdmin(false);

            throw new TenantException(
                'Your account is not associated with any clinic. Please contact support.',
                403,
                'TENANT_NOT_FOUND'
            );
        } catch (\Exception $e) {
            Log::error('SetTenant Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * ✅ تحسين #3: التحقق إذا كان الـ Route من ERP
     */
    private function isErpRoute(Request $request): bool
    {
        return str_contains($request->path(), 'erp/') || $request->is('api/erp/*');
    }

    /**
     * ✅ Debug logging - فقط في البيئة المحلية (تحسين #5)
     */
    private function logDebug(string $message, array $context = []): void
    {
        if (app()->environment('local', 'development')) {
            Log::debug($message, $context);
        }
    }

    /**
     * ✅ العثور على الشركة مع Cache (تحسين #5 - مدة أقل)
     */
    private function findCompany($companyId): ?Company
    {
        if (!$companyId) {
            return null;
        }

        $cacheKey = "company_{$companyId}_basic";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($companyId) {
            return Company::select('id', 'name', 'slug', 'status')->find($companyId);
        });
    }
}
