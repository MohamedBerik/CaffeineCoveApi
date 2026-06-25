<?php

namespace App\Services;

class AlertPolicyService
{
    public static function rolesFor(string $alertCode): array
    {
        return AlertDefinitionService::definition(
            $alertCode
        )['roles'];
    }
}
