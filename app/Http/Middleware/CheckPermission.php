<?php

namespace App\Http\Middleware;

use Closure;

class CheckPermission
{
    public function handle($request, Closure $next, $permission)
    {
        $user = $request->user();

        if (!$user || !$user->hasPermission($permission)) {
            return response()->json([
                'message' => 'Forbidden'
            ], 403);
        }

        return $next($request);
    }
}
