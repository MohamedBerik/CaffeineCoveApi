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

        if ($user) {
            // ✅ Super admin - no tenant context
            if ($user->is_super_admin) {
                Tenant::setId(null);
                Tenant::setIsSuperAdmin(true);
            }
            // ✅ Regular user - set company context
            elseif ($user->company_id) {
                Tenant::setId($user->company_id);
                Tenant::setIsSuperAdmin(false);
            }
            // ❌ User without company - should not happen
            else {
                Tenant::setId(null);
                Tenant::setIsSuperAdmin(false);
            }
        }

        return $next($request);
    }
}
