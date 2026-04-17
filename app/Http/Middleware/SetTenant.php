<?php
// app/Http/Middleware/SetTenant.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Tenant;

class SetTenant
{
    // app/Http/Middleware/SetTenant.php

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            // ✅ [إصلاح] تعيين صلاحية Super Admin مع الاحتفاظ بـ company_id إن وجد
            if ($user->is_super_admin) {
                Tenant::setIsSuperAdmin(true);
                // إذا كان لديه company_id (يعمل داخل شركة)، نضبط السياق عليها
                if ($user->company_id) {
                    Tenant::setId($user->company_id);
                } else {
                    Tenant::setId(null);
                }
            }
            // ✅ مستخدم عادي - نضبط سياق الشركة
            elseif ($user->company_id) {
                Tenant::setId($user->company_id);
                Tenant::setIsSuperAdmin(false);
            }
            // ❌ مستخدم بدون شركة (لا يجب أن يحدث)
            else {
                Tenant::setId(null);
                Tenant::setIsSuperAdmin(false);
            }
        }

        return $next($request);
    }
}
