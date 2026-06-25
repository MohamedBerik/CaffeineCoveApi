<?php

namespace App\Services;

use App\Constants\AlertCodes;
use App\Models\SystemAlert;

class AlertDefinitionService
{
    public static function definition(string $code): array
    {
        return match ($code) {

            AlertCodes::REMINDER_FAILED => [
                'priority' => SystemAlert::PRIORITY_HIGH,
                'type' => SystemAlert::TYPE_SYSTEM,
                'roles' => ['receptionist', 'doctor'],
                'icon' => '⚠️',
            ],

            AlertCodes::REMINDER_RETRY => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type' => SystemAlert::TYPE_SYSTEM,
                'roles' => ['receptionist', 'doctor'],
                'icon' => '🔄',
            ],

            AlertCodes::REMINDER_STUCK => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type' => SystemAlert::TYPE_SYSTEM,
                'roles' => ['receptionist', 'doctor'],
                'icon' => '⏳',
            ],

            AlertCodes::STOCK_LOW => [
                'priority' => SystemAlert::PRIORITY_HIGH,
                'type' => SystemAlert::TYPE_STOCK,
                'roles' => ['receptionist'],
                'icon' => '📦',
            ],

            AlertCodes::STOCK_OUT => [
                'priority' => SystemAlert::PRIORITY_CRITICAL,
                'type' => SystemAlert::TYPE_STOCK,
                'roles' => ['receptionist'],
                'icon' => '🚫',
            ],

            default => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type' => SystemAlert::TYPE_SYSTEM,
                'roles' => ['admin'],
                'icon' => '🔔',
            ],
        };
    }
}
