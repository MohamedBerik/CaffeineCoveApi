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
        // $this->reportable(function (Throwable $e) {
        //     Log::error($e->getMessage(), [
        //         'file' => $e->getFile(),
        //         'line' => $e->getLine(),
        //         'class' => get_class($e),
        //     ]);
        // });

        $this->renderable(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            $message = $e->getMessage() ?: 'This action is unauthorized.';
            return response()->json([
                'message' => $message,
            ], 403);
        });
    }

    // public function render($request, Throwable $e)
    // {
    //     // ✅ إرجاع تفاصيل الخطأ كاملة في الـ API
    //     if ($request->expectsJson() || $request->is('api/*')) {
    //         $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
    //         if ($statusCode < 100 || $statusCode > 599) {
    //             $statusCode = 500;
    //         }

    //         return response()->json([
    //             'message' => $e->getMessage(),
    //             'exception' => get_class($e),
    //             'file' => $e->getFile(),
    //             'line' => $e->getLine(),
    //             'status' => $statusCode,
    //         ], $statusCode);
    //     }

    //     return parent::render($request, $e);
    // }

    public function render($request, Throwable $e)
    {
        // ✅ التعامل مع AuthorizationException بشكل صحيح
        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            $statusCode = method_exists($e, 'status') ? $e->status() : 403;
            return response()->json([
                'message' => $e->getMessage() ?: 'This action is unauthorized.',
                'status' => $statusCode,
            ], $statusCode);
        }

        // ✅ لو الـ Request من API
        if ($request->expectsJson() || $request->is('api/*')) {
            $statusCode = method_exists($e, 'getStatusCode')
                ? $e->getStatusCode()
                : (method_exists($e, 'status') ? $e->status() : 500);

            if ($statusCode < 100 || $statusCode > 599) {
                $statusCode = 500;
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
