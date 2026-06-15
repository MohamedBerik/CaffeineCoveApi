<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
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
        // 1️⃣ [إصلاح حاسم] امسك خطأ عدم المصادقة فوراً وأجبره على 401 قبل الـ 500 العامة
        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return $this->unauthenticated($request, $e);
        }

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

        // استجابة عامة للـ API (للأخطاء غير المتوقعة الأخرى)
        if ($request->expectsJson() || $request->is('api/*')) {
            $statusCode = 500;

            if (method_exists($e, 'getStatusCode')) {
                $candidate = (int) $e->getStatusCode();

                if ($candidate >= 100 && $candidate <= 599) {
                    $statusCode = $candidate;
                }
            }

            if ($e instanceof \Illuminate\Database\QueryException) {

                Log::error($e);

                return response()->json([
                    'message' => 'Database error',
                ], 500);
            }

            return response()->json([
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'status' => $statusCode,
            ], $statusCode);
        }

        if (
            !($e instanceof \Illuminate\Validation\ValidationException) &&
            !($e instanceof \Illuminate\Auth\AuthenticationException)
        ) {
            event(
                new \App\Events\SystemExceptionOccurred(
                    $e->getMessage(),
                    get_class($e),
                    $e->getFile(),
                    $e->getLine()
                )
            );
        }

        return parent::render($request, $e);
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // 🎯 إرجاع رد 401 نظيف وصريح يفهمه الفرونت إند فوراً بدون انهيار السيرفر
        if ($request->expectsJson() || $request->is('api/*') || str_contains($request->url(), '/api/')) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'status' => 401
            ], 401);
        }

        return redirect()->guest($exception->redirectTo($request) ?? route('login'));
    }
}
