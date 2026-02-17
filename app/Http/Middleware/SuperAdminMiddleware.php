<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->is_super_admin) {
            return response()->json([
                'message' => 'Only super admin can access this resource'
            ], 403);
        }

        return $next($request);
    }
}
