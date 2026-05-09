<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$permissions
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        // ✅ Super Admin bypasses all permission checks
        if ($user->is_super_admin) {
            return $next($request);
        }

        // ✅ Check if user has any of the required permissions (OR condition)
        $hasPermission = false;
        $missingPermissions = [];

        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission, 'api')) {
                $hasPermission = true;
                break;
            }
            $missingPermissions[] = $permission;
        }

        if (!$hasPermission) {
            return response()->json([
                'message' => 'Unauthorized. You don\'t have the required permission.',
                'required_permissions' => $permissions,
                'missing_permissions' => $missingPermissions,
            ], 403);
        }

        return $next($request);
    }
}
