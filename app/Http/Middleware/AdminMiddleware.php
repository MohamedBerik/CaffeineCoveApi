<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user = auth()->user();

        // ✔ يسمح للـ admin داخل الشركة
        // ✔ ويسمح للـ super admin
        if (!$user->is_super_admin && $user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        return $next($request);
    }
}




//Old code
// namespace App\Http\Middleware;

// use Closure;
// use Illuminate\Http\Request;

// class AdminMiddleware
// {
//     public function handle(Request $request, Closure $next)
//     {
//         if (!auth()->check() || auth()->user()->role !== 'admin') {
//             return response()->json([
//                 'message' => 'Unauthorized'
//             ], 403);
//         }

//         return $next($request);
//     }
// }
