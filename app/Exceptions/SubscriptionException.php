<?php

namespace App\Exceptions;

class SubscriptionException extends ApiException
{
    public function __construct(
        string $message = 'Subscription error occurred',
        int $statusCode = 500,
        string $errorCode = 'SUBSCRIPTION_ERROR',
        array $context = [],
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $statusCode, $errorCode, $context, $previous);
    }
}
