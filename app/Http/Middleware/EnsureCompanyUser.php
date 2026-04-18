<?php
// app/Http/Middleware/EnsureCompanyUser.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Tenant;

class EnsureCompanyUser
{
    // app/Http/Middleware/EnsureCompanyUser.php


    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // if (!$user) {
        //     return response()->json(['message' => 'Unauthenticated'], 401);
        // }

        // 🔥 مؤقت
        if (!$user) {
            return $next($request);
        }

        // ✅ [إصلاح] السماح لـ Super Admin بالمرور فقط إذا لم يكن في سياق شركة
        if (Tenant::isSuperAdmin() && !Tenant::hasTenant()) {
            return $next($request);
        }

        // ✅ مستخدم عادي - يجب أن يكون لديه company_id
        if (!Tenant::hasTenant()) {
            return response()->json([
                'message' => 'User is not assigned to any company'
            ], 403);
        }

        return $next($request);
    }
}
