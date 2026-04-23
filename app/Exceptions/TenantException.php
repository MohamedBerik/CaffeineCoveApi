<?php

namespace App\Exceptions;

class TenantException extends ApiException
{
    public function __construct(
        string $message = 'Tenant context error',
        int $statusCode = 403,
        string $errorCode = 'TENANT_ERROR',
        array $context = [],
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $statusCode, $errorCode, $context, $previous);
    }
}
