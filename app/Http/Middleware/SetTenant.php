<?php
// app/Http/Middleware/SetTenant.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Tenant;

class SetTenant
{

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // if ($user) {
        //     if ($user->is_super_admin) {
        //         Tenant::setIsSuperAdmin(true);
        //         // ✅ لو Super Admin داخل شركة، نستخدم company_id بتاعه
        //         if ($user->company_id) {
        //             Tenant::setId($user->company_id);
        //         } else {
        //             Tenant::setId(null);
        //         }
        //     } elseif ($user->company_id) {
        //         Tenant::setId($user->company_id);
        //         Tenant::setIsSuperAdmin(false);
        //     } else {
        //         Tenant::setId(null);
        //         Tenant::setIsSuperAdmin(false);
        //     }
        // }


        if ($user->is_super_admin) {
            Tenant::setIsSuperAdmin(true);

            if ($user->company_id) {
                Tenant::setId($user->company_id);
            } else {
                Tenant::setId(1); // 👈 TEMP FIX
            }
        }
        return $next($request);
    }
}
