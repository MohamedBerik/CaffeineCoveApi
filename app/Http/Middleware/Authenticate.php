<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo($request)
    {
        // 🎯 فحص صارم ومباشر للمسار لمنع الـ 500 على فيرسيل
        if ($request->expectsJson() || $request->is('api/*') || str_contains($request->url(), '/api/')) {
            return null;
        }

        // تحصين إضافي: إذا كان الطلب للـ API ولم يمر بالشروط أعلاه، اقطعه بـ 401 فوراً
        abort(response()->json([
            'message' => 'Unauthenticated.',
            'status' => 401
        ], 401));
    }
}
