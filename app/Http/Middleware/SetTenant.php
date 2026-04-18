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
            // ✅ Super Admin
            if ($user->is_super_admin) {
                Tenant::setIsSuperAdmin(true);
                // 🔥 PATCH: دايمًا يبقى فيه tenant
                Tenant::setId($user->company_id ?? 1);
            }
            // ✅ User عادي
            elseif ($user->company_id) {
                Tenant::setId($user->company_id);
                Tenant::setIsSuperAdmin(false);
            }
            // ❗ fallback (مهم جدًا)
            else {
                Tenant::setId(1);
                Tenant::setIsSuperAdmin(false);
            }
        }

        return $next($request);
    }
}
