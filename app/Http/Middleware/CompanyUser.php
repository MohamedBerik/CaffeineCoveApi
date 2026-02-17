<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CompanyUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        // super admin لا يدخل ERP
        if ($user->is_super_admin) {
            return response()->json([
                'message' => 'Super admin cannot access company resources'
            ], 403);
        }

        // لازم يكون مرتبط بشركة
        if (!$user->company_id) {
            return response()->json([
                'message' => 'User is not assigned to any company'
            ], 403);
        }

        return $next($request);
    }
}
