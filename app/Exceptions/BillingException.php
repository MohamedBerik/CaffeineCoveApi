<?php

namespace App\Exceptions;

class BillingException extends ApiException
{
    public function __construct(
        string $message = 'Billing error occurred',
        int $statusCode = 500,
        string $errorCode = 'BILLING_ERROR',
        array $context = [],
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $statusCode, $errorCode, $context, $previous);
    }
}
