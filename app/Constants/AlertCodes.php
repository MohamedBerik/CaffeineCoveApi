<?php

namespace App\Constants;

class AlertCodes
{
    // =====================
    // Inventory
    // =====================
    const STOCK_LOW = 'stock.low';
    const STOCK_OUT = 'stock.out';

    // =====================
    // Payments
    // =====================
    const PAYMENT_RECEIVED = 'payment.received';
    const PAYMENT_FAILED = 'payment.failed';
    const PAYMENT_OVERDUE = 'payment.overdue';
    const PAYMENT_REFUND = 'payment.refund';

    // =====================
    // Orders
    // =====================
    const ORDER_CREATED = 'order.created';
    const ORDER_CONFIRMED = 'order.confirmed';
    const ORDER_CANCELLED = 'order.cancelled';

    // =====================
    // Appointments
    // =====================
    const APPOINTMENT_BOOKED = 'appointment.booked';
    const APPOINTMENT_CREATED = 'appointment.created';
    const APPOINTMENT_CANCELLED = 'appointment.cancelled';
    const APPOINTMENT_REMINDER = 'appointment.reminder';

    // =====================
    // Patients
    // =====================
    const PATIENT_NEW = 'patient.new';
    const PATIENT_RETURN = 'patient.return';

    // =====================
    // Treatments
    // =====================
    const TREATMENT_STARTED = 'treatment.started';
    const TREATMENT_COMPLETED = 'treatment.completed';

    // =====================
    // Reminder Monitoring
    // =====================
    const REMINDER_FAILED = 'reminder.failed';
    const REMINDER_RETRY = 'reminder.retry';
    const REMINDER_STUCK = 'reminder.stuck';

    // =====================
    // Subscription
    // =====================
    const TRIAL_EXPIRING = 'trial.expiring';
    const SUBSCRIPTION_EXPIRED = 'subscription.expired';

    // =====================
    // Security
    // =====================
    const SECURITY_LOGIN_FAILED = 'security.login_failed';
    const SECURITY_SUSPICIOUS_ACTIVITY = 'security.suspicious_activity';

    /**
     * Alert icons.
     */
    public static function getIcon(string $code): string
    {
        return [
            // Inventory
            self::STOCK_LOW => '📦',
            self::STOCK_OUT => '🚫',

            // Payments
            self::PAYMENT_RECEIVED => '💰',
            self::PAYMENT_FAILED => '💳',
            self::PAYMENT_OVERDUE => '⏰',
            self::PAYMENT_REFUND => '↩️',

            // Orders
            self::ORDER_CREATED => '🛒',
            self::ORDER_CONFIRMED => '✅',
            self::ORDER_CANCELLED => '❌',

            // Appointments
            self::APPOINTMENT_BOOKED => '📅',
            self::APPOINTMENT_CREATED => '📅',
            self::APPOINTMENT_CANCELLED => '❌',
            self::APPOINTMENT_REMINDER => '⏰',

            // Patients
            self::PATIENT_NEW => '👤',
            self::PATIENT_RETURN => '🔄',

            // Treatments
            self::TREATMENT_STARTED => '🔧',
            self::TREATMENT_COMPLETED => '✅',

            // Reminder Monitoring
            self::REMINDER_FAILED => '⚠️',
            self::REMINDER_RETRY => '🔄',
            self::REMINDER_STUCK => '⏳',

            // Subscription
            self::TRIAL_EXPIRING => '⌛',
            self::SUBSCRIPTION_EXPIRED => '🚫',

            // Security
            self::SECURITY_LOGIN_FAILED => '🔐',
            self::SECURITY_SUSPICIOUS_ACTIVITY => '🚨',
        ][$code] ?? '🔔';
    }
}
