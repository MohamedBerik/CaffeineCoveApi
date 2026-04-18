<?php
// app/Http/Middleware/SetTenant.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Tenant;
use Illuminate\Support\Facades\Log;

class SetTenant
{

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        Log::info('USER IN TENANT', ['user' => $user]);

        if (!$user) {
            return $next($request);
        }

        if ($user->is_super_admin) {
            Tenant::setIsSuperAdmin(true);

            if ($user->company_id) {
                Tenant::setId($user->company_id);
            } else {
                Tenant::setId(null); // ❗ مش 1
            }
        } else {
            Tenant::setId($user->company_id);
            Tenant::setIsSuperAdmin(false);
        }

        return $next($request);
    }
}
