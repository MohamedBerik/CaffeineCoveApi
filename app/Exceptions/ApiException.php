<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiException extends Exception
{
    protected $statusCode;
    protected $errorCode;
    protected $context;

    public function __construct(
        string $message = 'Something went wrong',
        int $statusCode = 500,
        string $errorCode = 'SERVER_ERROR',
        array $context = [],
        ?Exception $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getSafeMessage(),
            'code' => $this->errorCode,
            'status' => $this->statusCode,
        ], $this->statusCode);
    }

    public function getSafeMessage(): string
    {
        // في Production - رسالة آمنة
        if (app()->environment('production')) {
            return match ($this->statusCode) {
                401 => 'Unauthenticated.',
                403 => 'Unauthorized.',
                404 => 'Resource not found.',
                422 => 'Validation failed.',
                429 => 'Too many requests. Please try again later.',
                500 => 'Something went wrong. Please try again later.',
                default => 'An error occurred.',
            };
        }

        // في Development - رسالة تفصيلية
        return $this->getMessage();
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
