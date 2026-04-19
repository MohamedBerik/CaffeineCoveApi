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
            // ✅ Super Admin
            if ($user->is_super_admin) {
                Tenant::setIsSuperAdmin(true);

                // ✅ قراءة الشركة المختارة من session
                $sessionCompany = session('tenant_id');

                if ($sessionCompany) {
                    Tenant::setId($sessionCompany);
                } else {
                    Tenant::setId(null);
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

        return $next($request);
    }
}
