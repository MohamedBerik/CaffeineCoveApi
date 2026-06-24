<?php

namespace App\Services;

class AlertPolicyService
{
    public static function rolesFor(string $alertCode): array
    {
        return match ($alertCode) {

            // المخزون
            'stock.low',
            'stock.out' =>
            ['receptionist'],

            // التذكيرات
            'reminder.failed',
            'reminder.retry',
            'reminder.stuck' =>
            ['receptionist', 'doctor'],

            // المدفوعات
            'payment.failed',
            'payment.refund' =>
            ['receptionist'],

            // المواعيد
            'appointment.created',
            'appointment.cancelled' =>
            ['receptionist', 'doctor'],

            // الاشتراك
            'trial.expiring',
            'subscription.expired' =>
            ['admin'],

            // الأمان
            'security.login_failed',
            'security.suspicious_activity' =>
            ['admin'],

            default =>
            ['admin'],
        };
    }
}
