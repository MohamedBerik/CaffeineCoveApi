<?php

return [
    // Inventory
    'stock.low'               => 'Stock is low for :product_name (:quantity remaining)',
    'stock.out'               => ':product_name is out of stock',

    // Payments
    'payment.received'        => 'Payment of :amount received',
    'payment.failed'          => 'Payment failed for invoice #:invoice',
    'payment.overdue'         => 'Payment of :amount is overdue',
    'payment.refund'          => 'Refund processed for invoice #:invoice',

    // Orders
    'order.created'           => 'New order #:order_number created',
    'order.confirmed'         => 'Order #:order_number confirmed',
    'order.cancelled'         => 'Order #:order_number cancelled',

    // Appointments
    'appointment.booked'      => 'New appointment booked for Dr. :doctor_name',
    'appointment.created'     => 'New appointment assigned to Dr. :doctor_name',
    'appointment.cancelled'   => 'Appointment cancelled',
    'appointment.reminder'    => 'Appointment reminder',

    // Patients
    'patient.new'             => 'New patient :patient_name registered',
    'patient.return'          => 'Patient :patient_name returned',

    // Treatments
    'treatment.started'       => 'Treatment started for :patient_name',
    'treatment.completed'     => 'Treatment completed for :patient_name',

    // Reminders
    'reminder.failed'         => ':count reminder(s) failed (threshold :threshold)',
    'reminder.retry'          => ':count reminder(s) retrying (threshold :threshold)',
    'reminder.stuck'          => ':count reminder(s) stuck in processing (threshold :threshold)',

    // Subscription
    'trial.expiring'          => 'Your trial will expire in :days day(s)',
    'subscription.expired'    => 'Your subscription has expired',

    // Security
    'security.login_failed'   => 'Failed login attempt detected',
    'security.suspicious_activity' => 'Suspicious activity detected on your account',

    // Fallback
    'default'                 => 'System notification',
];
