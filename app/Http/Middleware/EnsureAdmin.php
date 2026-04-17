<?php
// app/Http/Middleware/EnsureAdmin.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Tenant;

class EnsureAdmin
{
    // app/Http/Middleware/EnsureAdmin.php
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // ✅ [إصلاح] استخدام Tenant للتحقق من الصلاحية
        if (!Tenant::isSuperAdmin() && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        return $next($request);
    }
}
