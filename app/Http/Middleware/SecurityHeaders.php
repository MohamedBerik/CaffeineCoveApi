<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        // 1. لو الطلب OPTIONS، اخرج فوراً واترك المهمة لـ HandleCors
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $response = $next($request);

        // 2. تأكد أن الاستجابة صالحة قبل إضافة الهيدرز
        if (!method_exists($response, 'header')) {
            return $response;
        }

        // أضف الهيدرز العادية لكن خفف الـ CSP مؤقتاً
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN'); // غيرها لـ SAMEORIGIN بدل DENY
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        return $response;
    }
}
