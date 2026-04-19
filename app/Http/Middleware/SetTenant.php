<?php
// app/Http/Middleware/SetTenant.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Tenant;
use Illuminate\Support\Facades\Log;

class SetTenant
{

    // app/Http/Middleware/SetTenant.php

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            $tenantIdFromHeader = $request->header('X-Tenant-ID');

            // ✅ Super Admin
            if ($user->is_super_admin) {
                Tenant::setIsSuperAdmin(true);

                if ($tenantIdFromHeader) {
                    Tenant::setId($tenantIdFromHeader);
                } else {
                    Tenant::setId(null); // Global Mode
                }
            }
            // ✅ مستخدم عادي
            elseif ($user->company_id) {
                Tenant::setId($user->company_id);
                Tenant::setIsSuperAdmin(false);
            }
            // ❌ مستخدم بدون شركة
            else {
                Tenant::setId(null);
                Tenant::setIsSuperAdmin(false);
            }
        }

        // ✅ Debug Log
        Log::info('Tenant Check', [
            'tenant_id' => Tenant::id(),
            'header' => $request->header('X-Tenant-ID'),
            'user_id' => $user->id ?? null,
        ]);

        return $next($request);
    }
}
