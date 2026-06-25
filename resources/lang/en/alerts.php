<?php

return [

    // Reminders
    'reminder.failed'
    => 'High failed reminders (:count failures, threshold :threshold)',

    'reminder.retry'
    => 'High retry reminders (:count retries, threshold :threshold)',

    'reminder.stuck'
    => 'Stuck processing reminders (:count stuck, threshold :threshold)',

    // Stock
    'stock.low'
    => 'Stock is low for :item (:quantity remaining)',

    'stock.out'
    => ':item is out of stock',

    // Payments
    'payment.failed'
    => 'Payment failed for invoice #:invoice',

    'payment.refund'
    => 'Refund processed for invoice #:invoice',

    // Appointments
    'appointment.created'
    => 'New appointment created for :patient',

    'appointment.cancelled'
    => 'Appointment cancelled for :patient',

    // Subscription
    'trial.expiring'
    => 'Your trial will expire in :days days',

    'subscription.expired'
    => 'Your subscription has expired',

    // Security
    'security.login_failed'
    => 'Failed login attempt detected',

    'security.suspicious_activity'
    => 'Suspicious activity detected on your account',

    // Fallback
    'default'
    => 'System notification',

];
