<?php
// app/Http/Middleware/EnsureCompanyUser.php

namespace App\Http\Middleware;

use App\Services\Tenant;
use Closure;
use Illuminate\Http\Request;

class EnsureCompanyUser
{
    public function handle($request, $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // ✅ Super admin يعدي
        if ($user->is_super_admin) {
            Tenant::setId(null); // ✅ مهم
            Tenant::setIsSuperAdmin(true);
            return $next($request);
        }

        // ❌ مستخدم عادي بدون شركة
        if (!$user->company_id) {
            return response()->json(['message' => 'User is not assigned to any company'], 403);
        }

        // ✅ تعيين Tenant Context
        Tenant::setId($user->company_id);
        Tenant::setIsSuperAdmin(false);

        return $next($request);
    }
}
