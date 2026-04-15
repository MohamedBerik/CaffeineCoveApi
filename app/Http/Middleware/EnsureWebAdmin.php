<?php
// app/Http/Middleware/EnsureWebAdmin.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureWebAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // ✅ Super admin or company admin
        if ($user->is_super_admin || $user->role === 'admin') {
            return $next($request);
        }

        // ❌ Regular user - redirect to dashboard
        if ($user->role === 'user') {
            return redirect()->route('dashboard');
        }

        return redirect()->route('login');
    }
}
