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
        // ✅ أضف السطر ده مؤقتًا عشان تشوف الـ Error
        return response()->json([
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => 'SERVER_ERROR',
            'status' => 500,
        ], 500);
        // ✅ لو الـ Exception بتاعنا (ApiException أو اللي ورث منها)
        if ($e instanceof ApiException) {
            return $e->render();
        }

        // ✅ لو Rate Limiting
        if ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'code' => 'TOO_MANY_REQUESTS',
                'status' => 429,
                'retry_after' => $e->getHeaders()['Retry-After'] ?? 60,
            ], 429);
        }

        // ✅ لو الـ Request API
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->renderApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Render API Exception with safe response
     */
    protected function renderApiException($request, Throwable $e): JsonResponse
    {
        // ✅ أضف السطر ده مؤقتًا عشان تشوف الـ Error
        return response()->json([
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => 'SERVER_ERROR',
            'status' => 500,
        ], 500);

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
