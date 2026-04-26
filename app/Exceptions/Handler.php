<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];
    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * ✅ تسجيل كل الأخطاء مع تفاصيل كاملة
     */
    public function render($request, Throwable $e)
    {
        // ✅ تسجيل تفاصيل الخطأ بالكامل
        Log::error('GLOBAL ERROR HANDLER', [
            'path' => $request->path(),
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'class' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]);

        // ✅ في الإنتاج: إرجاع JSON مفيد
        return response()->json([
            'error' => 'Server Error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'class' => get_class($e),
        ], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Credentials', 'true');
    }
}
