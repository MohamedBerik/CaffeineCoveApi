<?php
// app/Http/Middleware/EnsureSuperAdmin.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Tenant;

class EnsureSuperAdmin
{
    // app/Http/Middleware/EnsureSuperAdmin.php


    public function handle(Request $request, Closure $next)
    {
        // ✅ [إصلاح] استخدام Tenant للتحقق من الصلاحية
        if (!Tenant::isSuperAdmin()) {
            return response()->json([
                'message' => 'Only super admin can access this resource'
            ], 403);
        }

        return $next($request);
    }
}
