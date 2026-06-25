<?php

return [
    // المخزون
    'stock.low'               => 'المخزون منخفض للصنف :product_name والمتبقي :quantity.',
    'stock.out'               => 'نفد مخزون الصنف :product_name.',

    // المدفوعات
    'payment.received'        => 'تم استلام دفعة بقيمة :amount.',
    'payment.failed'          => 'فشل سداد مبلغ :amount.',
    'payment.overdue'         => 'دفعة :amount متأخرة عن السداد.',
    'payment.refund'          => 'تم استرداد مبلغ :amount.',

    // الطلبات
    'order.created'           => 'تم إنشاء طلب جديد رقم :order_number.',
    'order.confirmed'         => 'تم تأكيد الطلب رقم :order_number.',
    'order.cancelled'         => 'تم إلغاء الطلب رقم :order_number.',

    // المواعيد
    'appointment.booked'      => 'تم حجز موعد جديد للدكتور :doctor_name.',
    'appointment.created'     => 'تم إسناد موعد جديد إلى د. :doctor_name.',
    'appointment.cancelled'   => 'تم إلغاء الموعد.',
    'appointment.reminder'    => 'تذكير بموعد.',

    // المرضى
    'patient.new'             => 'تم تسجيل مريض جديد :patient_name.',
    'patient.return'          => 'عاد المريض :patient_name للعيادة.',

    // العلاجات
    'treatment.started'       => 'بدأ علاج المريض :patient_name.',
    'treatment.completed'     => 'اكتمل علاج المريض :patient_name.',

    // التذكيرات
    'reminder.failed'         => 'فشل إرسال :count تذكير (الحد :threshold).',
    'reminder.retry'          => 'يوجد :count تذكير يحتاج إعادة المحاولة (الحد :threshold).',
    'reminder.stuck'          => 'يوجد :count تذكير عالق قيد المعالجة (الحد :threshold).',

    // الاشتراك
    'trial.expiring'          => 'سينتهي الاشتراك التجريبي خلال :days يوم.',
    'subscription.expired'    => 'انتهى اشتراك الشركة.',

    // الأمان
    'security.login_failed'   => 'تم اكتشاف محاولة تسجيل دخول فاشلة.',
    'security.suspicious_activity' => 'تم اكتشاف نشاط مشبوه في الحساب.',

    // افتراضي
    'default'                 => 'إشعار من النظام',
];
