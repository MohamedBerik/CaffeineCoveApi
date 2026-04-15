<?php
// app/Http/Middleware/EnsureSuperAdmin.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->is_super_admin) {
            return response()->json([
                'message' => 'Only super admin can access this resource'
            ], 403);
        }

        return $next($request);
    }
}
