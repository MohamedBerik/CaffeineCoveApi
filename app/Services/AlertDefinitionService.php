<?php

namespace App\Services;

use App\Constants\AlertCodes;
use App\Models\SystemAlert;

class AlertDefinitionService
{
    public static function definition(string $code): array
    {
        return match ($code) {

            // Inventory
            AlertCodes::STOCK_LOW => [
                'priority' => SystemAlert::PRIORITY_HIGH,
                'type'     => SystemAlert::TYPE_STOCK,
                'roles'    => ['receptionist'],
                'icon'     => '📦',
            ],
            AlertCodes::STOCK_OUT => [
                'priority' => SystemAlert::PRIORITY_CRITICAL,
                'type'     => SystemAlert::TYPE_STOCK,
                'roles'    => ['receptionist'],
                'icon'     => '🚫',
            ],

            // Payments
            AlertCodes::PAYMENT_RECEIVED => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_PAYMENT,
                'roles'    => ['receptionist', 'admin'],
                'icon'     => '💰',
            ],
            AlertCodes::PAYMENT_FAILED => [
                'priority' => SystemAlert::PRIORITY_HIGH,
                'type'     => SystemAlert::TYPE_PAYMENT,
                'roles'    => ['receptionist', 'admin'],
                'icon'     => '💳',
            ],
            AlertCodes::PAYMENT_OVERDUE => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_PAYMENT,
                'roles'    => ['receptionist', 'admin'],
                'icon'     => '⏰',
            ],
            AlertCodes::PAYMENT_REFUND => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_PAYMENT,
                'roles'    => ['receptionist', 'admin'],
                'icon'     => '↩️',
            ],

            // Orders
            AlertCodes::ORDER_CREATED => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['admin'],
                'icon'     => '🛒',
            ],
            AlertCodes::ORDER_CONFIRMED => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['admin'],
                'icon'     => '✅',
            ],
            AlertCodes::ORDER_CANCELLED => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['admin'],
                'icon'     => '❌',
            ],

            // Appointments
            AlertCodes::APPOINTMENT_BOOKED => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_APPOINTMENT,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '📅',
            ],
            AlertCodes::APPOINTMENT_CREATED => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_APPOINTMENT,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '📅',
            ],
            AlertCodes::APPOINTMENT_CANCELLED => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_APPOINTMENT,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '❌',
            ],
            AlertCodes::APPOINTMENT_REMINDER => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_APPOINTMENT,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '⏰',
            ],

            // Patients
            AlertCodes::PATIENT_NEW => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['receptionist'],
                'icon'     => '👤',
            ],
            AlertCodes::PATIENT_RETURN => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['receptionist'],
                'icon'     => '🔄',
            ],

            // Treatments
            AlertCodes::TREATMENT_STARTED => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '🔧',
            ],
            AlertCodes::TREATMENT_COMPLETED => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '✅',
            ],

            // Reminder Monitoring
            AlertCodes::REMINDER_FAILED => [
                'priority' => SystemAlert::PRIORITY_HIGH,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '⚠️',
            ],
            AlertCodes::REMINDER_RETRY => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '🔄',
            ],
            AlertCodes::REMINDER_STUCK => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '⏳',
            ],

            // Subscription
            AlertCodes::TRIAL_EXPIRING => [
                'priority' => SystemAlert::PRIORITY_HIGH,
                'type'     => SystemAlert::TYPE_TRIAL,
                'roles'    => ['admin'],
                'icon'     => '⌛',
            ],
            AlertCodes::SUBSCRIPTION_EXPIRED => [
                'priority' => SystemAlert::PRIORITY_CRITICAL,
                'type'     => SystemAlert::TYPE_TRIAL,
                'roles'    => ['admin'],
                'icon'     => '🚫',
            ],

            // Security
            AlertCodes::SECURITY_LOGIN_FAILED => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_SECURITY,
                'roles'    => ['admin'],
                'icon'     => '🔐',
            ],
            AlertCodes::SECURITY_SUSPICIOUS_ACTIVITY => [
                'priority' => SystemAlert::PRIORITY_HIGH,
                'type'     => SystemAlert::TYPE_SECURITY,
                'roles'    => ['admin'],
                'icon'     => '🚨',
            ],

            // أثناء التطوير نفضل رمي استثناء لاكتشاف الأخطاء الإملائية فوراً
            default => throw new \InvalidArgumentException(
                "Undefined alert code: {$code}"
            ),
        };
    }
}
