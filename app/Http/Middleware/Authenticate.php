<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // 🎯 إجبار لارفيل على إرجاع خطأ مصادقة نظيف إذا كان المسار يحتوي على api (حتى لو لم يقرأ الـ Headers على Vercel)
        if ($request->expectsJson() || $request->is('api/*') || str_contains($request->url(), '/api/')) {
            return null;
        }

        // تحصين إضافي: إذا حاول لارفيل الانهيار والبحث عن صفحة الويب، اقطع الطلب بـ 401 فوراً
        abort(response()->json([
            'message' => 'Unauthenticated.',
            'status' => 401
        ], 401));
    }
}
