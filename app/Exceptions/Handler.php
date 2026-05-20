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

    public function register(): void
    {

        $this->renderable(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            $message = $e->getMessage() ?: 'This action is unauthorized.';
            return response()->json([
                'message' => $message,
            ], 403);
        });
    }

    protected function invalidJson($request, \Illuminate\Validation\ValidationException $exception)
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'errors' => $exception->errors(),
        ], 422);
    }

    public function render($request, Throwable $e)
    {
        // التعامل مع ValidationException
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return $this->invalidJson($request, $e);
        }

        // التعامل مع AuthorizationException
        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return response()->json([
                'message' => $e->getMessage() ?: 'This action is unauthorized.',
            ], 403);
        }

        // استجابة عامة للـ API
        if ($request->expectsJson() || $request->is('api/*')) {
            $statusCode = 500;
            if (method_exists($e, 'getStatusCode')) {
                $statusCode = $e->getStatusCode();
            } elseif (method_exists($e, 'getCode') && $e->getCode() > 0) {
                $statusCode = $e->getCode();
            }

            return response()->json([
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'status' => $statusCode,
            ], $statusCode);
        }

        return parent::render($request, $e);
    }
}
