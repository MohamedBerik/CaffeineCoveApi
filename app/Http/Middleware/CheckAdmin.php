<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckAdmin
{

    public function handle(Request $request, Closure $next)
    {
        if (Auth::user() && Auth::user()->role == "admin") {

            return $next($request);
        } else if (Auth::user() && Auth::user()->role == "user") {
            return redirect()->route("welcome");
        } else {
            return redirect()->route("login");
        }
    }
}
