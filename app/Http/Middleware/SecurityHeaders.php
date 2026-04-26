<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        // 1. لو الطلب OPTIONS (Preflight) مرره فوراً وسيبه لـ HandleCors
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $response = $next($request);

        // 2. تأكد إن الاستجابة كائن صالح قبل إضافة الهيدرز
        if (!method_exists($response, 'headers')) {
            return $response;
        }

        // 3. تعطيل الـ CSP مؤقتاً للتأكد من المشكلة
        // $response->headers->set('Content-Security-Policy', "...");

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        return $response;
    }
}
