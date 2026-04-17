<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCompanyUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // ✅ Super admin يعدي (حتى لو company_id = null)
        if ($user->is_super_admin) {
            return $next($request);
        }

        // ❌ مستخدم عادي بدون شركة - ممنوع
        if (!$user->company_id) {
            return response()->json([
                'message' => 'User is not assigned to any company'
            ], 403);
        }

        return $next($request);
    }
}
