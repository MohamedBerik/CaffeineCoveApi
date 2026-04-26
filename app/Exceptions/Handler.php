<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */

    public function render($request, Throwable $e)
    {
        // ✅ تسجيل تفاصيل الخطأ
        Log::error('API Error', [
            'path' => $request->path(),
            'method' => $request->getMethod(),
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        // ✅ إرجاع JSON مع CORS headers
        return response()->json([
            'error' => 'Server Error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500)
            ->header('Access-Control-Allow-Origin', $request->header('Origin', '*'))
            ->header('Access-Control-Allow-Credentials', 'true');
    }

    /**
     * Render API Exception with safe response
     */
    protected function renderApiException($request, Throwable $e): JsonResponse
    {
        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

        // ✅ تحديد الـ Status Code
        if ($statusCode === 0 || $statusCode > 599) {
            $statusCode = 500;
        }

        // ✅ Error Code موحد
        $errorCode = match ($statusCode) {
            401 => 'UNAUTHENTICATED',
            403 => 'UNAUTHORIZED',
            404 => 'NOT_FOUND',
            422 => 'VALIDATION_ERROR',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'SERVER_ERROR',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'UNKNOWN_ERROR',
        };

        // ✅ رسالة آمنة
        $message = $this->getSafeMessage($e, $statusCode);

        // ✅ Log الخطأ الحقيقي للمطورين
        $this->logError($e, $request);

        // ✅ Response آمن
        return response()->json([
            'message' => $message,
            'code' => $errorCode,
            'status' => $statusCode,
        ], $statusCode);
    }

    /**
     * Get safe message for production
     */
    protected function getSafeMessage(Throwable $e, int $statusCode): string
    {
        if (app()->environment('production')) {
            return match ($statusCode) {
                401 => 'Unauthenticated.',
                403 => 'Unauthorized.',
                404 => 'Resource not found.',
                422 => 'Validation failed.',
                429 => 'Too many requests. Please try again later.',
                500 => 'Something went wrong. Please try again later.',
                503 => 'Service temporarily unavailable. Please try again later.',
                default => 'An error occurred.',
            };
        }

        // في Development - نرجع رسالة الخطأ الأصلية
        return $e->getMessage();
    }

    /**
     * Log the real error for developers
     */
    protected function logError(Throwable $e, $request): void
    {
        $context = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => auth()->id() ?? 'guest',
            'tenant_id' => \App\Services\Tenant::id(),
            'ip' => $request->ip(),
            'trace' => $e->getTraceAsString(),
        ];

        // ✅ Log حسب شدة الخطأ
        if (
            $e instanceof \Illuminate\Http\Client\RequestException ||
            $e instanceof \Illuminate\Database\QueryException
        ) {
            Log::error($e->getMessage(), $context);
        } elseif ($e instanceof \Illuminate\Validation\ValidationException) {
            Log::warning($e->getMessage(), $context);
        } else {
            Log::error($e->getMessage(), $context);
        }
    }
}
