<?php

namespace App\Exceptions;

use App\Services\Tenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
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
            // ✅ تسجيل الأخطاء مع Tenant Context
            if (Tenant::hasTenant() && app()->environment('production')) {
                Log::error('Exception in tenant context', [
                    'company_id' => Tenant::id(),
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                    'ip' => request()->ip(),
                ]);
            }
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    // app/Exceptions/Handler.php

    public function render($request, Throwable $e)
    {
        return response()->json([
            'error' => $e->getMessage(),
        ], 500);
    }

    /**
     * Handle API exceptions with unified response format
     */
    protected function handleApiException($request, Throwable $e)
    {
        $statusCode = 500;
        $message = 'Server Error';
        $errors = null;

        if ($e instanceof AuthenticationException) {
            $statusCode = 401;
            $message = 'Unauthenticated';
        } elseif ($e instanceof AuthorizationException) {
            $statusCode = 403;
            $message = 'Unauthorized';
        } elseif ($e instanceof ModelNotFoundException) {
            $statusCode = 404;
            $message = 'Resource not found';
        } elseif ($e instanceof NotFoundHttpException) {
            $statusCode = 404;
            $message = 'Endpoint not found';
        } elseif ($e instanceof ValidationException) {
            $statusCode = 422;
            $message = 'Validation failed';
            $errors = $e->errors();
        } elseif ($e instanceof ThrottleRequestsException) {
            $statusCode = 429;
            $message = 'Too many requests';
        } elseif ($e instanceof \Illuminate\Database\QueryException) {
            if ((string) $e->getCode() === '23000') {
                $statusCode = 409;
                $message = 'Duplicate entry or constraint violation';
            }
        }

        $response = [
            'msg' => $message,
            'status' => $statusCode,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        // ✅ إضافة تفاصيل في بيئة التطوير
        if (app()->environment('local', 'development') && !$e instanceof ValidationException) {
            $response['debug'] = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        // ✅ إضافة Tenant Context في الـ Response (للتتبع)
        if (Tenant::hasTenant()) {
            $response['tenant_id'] = Tenant::id();
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json([
            'msg' => 'Unauthenticated',
            'status' => 401,
        ], 401);
    }
}
