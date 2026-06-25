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
                'title'    => 'Stock Low',
                'message'  => 'Stock level is low for :product_name',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::STOCK_OUT => [
                'priority' => SystemAlert::PRIORITY_CRITICAL,
                'type'     => SystemAlert::TYPE_STOCK,
                'roles'    => ['receptionist'],
                'icon'     => '🚫',
                'title'    => 'Stock Out',
                'message'  => 'Product :product_name is out of stock',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],

            // Payments
            AlertCodes::PAYMENT_RECEIVED => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_PAYMENT,
                'roles'    => ['receptionist', 'admin'],
                'icon'     => '💰',
                'title'    => 'Payment Received',
                'message'  => 'Payment of :amount received',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::PAYMENT_FAILED => [
                'priority' => SystemAlert::PRIORITY_HIGH,
                'type'     => SystemAlert::TYPE_PAYMENT,
                'roles'    => ['receptionist', 'admin'],
                'icon'     => '💳',
                'title'    => 'Payment Failed',
                'message'  => 'Payment of :amount failed',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::PAYMENT_OVERDUE => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_PAYMENT,
                'roles'    => ['receptionist', 'admin'],
                'icon'     => '⏰',
                'title'    => 'Payment Overdue',
                'message'  => 'Payment of :amount is overdue',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::PAYMENT_REFUND => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_PAYMENT,
                'roles'    => ['receptionist', 'admin'],
                'icon'     => '↩️',
                'title'    => 'Payment Refunded',
                'message'  => 'Refund of :amount processed',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],

            // Orders
            AlertCodes::ORDER_CREATED => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['admin'],
                'icon'     => '🛒',
                'title'    => 'Order Created',
                'message'  => 'New order #:order_number created',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::ORDER_CONFIRMED => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['admin'],
                'icon'     => '✅',
                'title'    => 'Order Confirmed',
                'message'  => 'Order #:order_number confirmed',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::ORDER_CANCELLED => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['admin'],
                'icon'     => '❌',
                'title'    => 'Order Cancelled',
                'message'  => 'Order #:order_number cancelled',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],

            // Appointments
            AlertCodes::APPOINTMENT_BOOKED => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_APPOINTMENT,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '📅',
                'title'    => 'Appointment Booked',
                'message'  => 'New appointment booked for Dr. :doctor_name',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::APPOINTMENT_CREATED => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_APPOINTMENT,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '📅',
                'title'    => 'Appointment Created',
                'message'  => 'New appointment assigned to Dr. :doctor_name',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::APPOINTMENT_CANCELLED => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_APPOINTMENT,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '❌',
                'title'    => 'Appointment Cancelled',
                'message'  => 'Appointment cancelled',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::APPOINTMENT_REMINDER => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_APPOINTMENT,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '⏰',
                'title'    => 'Appointment Reminder',
                'message'  => 'Appointment reminder',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],

            // Patients
            AlertCodes::PATIENT_NEW => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['receptionist'],
                'icon'     => '👤',
                'title'    => 'New Patient',
                'message'  => 'New patient :patient_name registered',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::PATIENT_RETURN => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['receptionist'],
                'icon'     => '🔄',
                'title'    => 'Patient Returned',
                'message'  => 'Patient :patient_name returned',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],

            // Treatments
            AlertCodes::TREATMENT_STARTED => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '🔧',
                'title'    => 'Treatment Started',
                'message'  => 'Treatment started for :patient_name',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::TREATMENT_COMPLETED => [
                'priority' => SystemAlert::PRIORITY_LOW,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '✅',
                'title'    => 'Treatment Completed',
                'message'  => 'Treatment completed for :patient_name',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],

            // Reminder Monitoring
            AlertCodes::REMINDER_FAILED => [
                'priority' => SystemAlert::PRIORITY_HIGH,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '⚠️',
                'title'    => 'Reminder Failed',
                'message'  => ':count reminder(s) failed',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::REMINDER_RETRY => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '🔄',
                'title'    => 'Reminder Retry',
                'message'  => ':count reminder(s) retrying',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::REMINDER_STUCK => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_SYSTEM,
                'roles'    => ['receptionist', 'doctor'],
                'icon'     => '⏳',
                'title'    => 'Reminder Stuck',
                'message'  => ':count reminder(s) stuck in processing',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],

            // Subscription
            AlertCodes::TRIAL_EXPIRING => [
                'priority' => SystemAlert::PRIORITY_HIGH,
                'type'     => SystemAlert::TYPE_TRIAL,
                'roles'    => ['admin'],
                'icon'     => '⌛',
                'title'    => 'Trial Expiring',
                'message'  => 'Your trial expires in :days day(s)',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::SUBSCRIPTION_EXPIRED => [
                'priority' => SystemAlert::PRIORITY_CRITICAL,
                'type'     => SystemAlert::TYPE_TRIAL,
                'roles'    => ['admin'],
                'icon'     => '🚫',
                'title'    => 'Subscription Expired',
                'message'  => 'Your subscription has expired',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],

            // Security
            AlertCodes::SECURITY_LOGIN_FAILED => [
                'priority' => SystemAlert::PRIORITY_MEDIUM,
                'type'     => SystemAlert::TYPE_SECURITY,
                'roles'    => ['admin'],
                'icon'     => '🔐',
                'title'    => 'Failed Login',
                'message'  => 'Failed login attempt detected',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],
            AlertCodes::SECURITY_SUSPICIOUS_ACTIVITY => [
                'priority' => SystemAlert::PRIORITY_HIGH,
                'type'     => SystemAlert::TYPE_SECURITY,
                'roles'    => ['admin'],
                'icon'     => '🚨',
                'title'    => 'Suspicious Activity',
                'message'  => 'Suspicious activity detected',
                'channels' => [
                    'in_app',
                    'email'
                ]
            ],

            // أثناء التطوير نفضل رمي استثناء لاكتشاف الأخطاء الإملائية فوراً
            default => throw new \InvalidArgumentException(
                "Undefined alert code: {$code}"
            ),
        };
    }
}
