<?php
// app/Http/Middleware/EnsureWebAdmin.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Tenant;

class EnsureWebAdmin
{
    // app/Http/Middleware/EnsureWebAdmin.php


    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // ✅ [إصلاح] استخدام Tenant للتحقق من الصلاحية
        if (Tenant::isSuperAdmin() || $user->role === 'admin') {
            return $next($request);
        }

        if ($user->role === 'user') {
            return redirect()->route('dashboard');
        }

        return redirect()->route('login');
    }
}
