<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $levels = [];
    protected $dontReport = [];
    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    public function register()
    {
        // ✅ تسجيل كل الأخطاء
        $this->reportable(function (Throwable $e) {
            Log::error('Exception: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'class' => get_class($e),
            ]);
        });
    }

    public function render($request, Throwable $e)
    {
        // ✅ في الإنتاج: أرجع تفاصيل الخطأ عشان نشوفه
        if (app()->environment('production')) {
            return response()->json([
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }

        // ✅ لو الـ Exception بتاعنا
        if ($e instanceof ApiException) {
            return $e->render();
        }

        // ✅ لو Rate Limiting
        if ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
            return response()->json([
                'message' => 'Too many requests.',
                'retry_after' => $e->getHeaders()['Retry-After'] ?? 60,
            ], 429);
        }

        return parent::render($request, $e);
    }
}
