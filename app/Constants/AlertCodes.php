<?php

namespace App\Constants;

class AlertCodes
{
    // المخزون
    const LOW_STOCK = 'LOW_STOCK';
    const OUT_OF_STOCK = 'OUT_OF_STOCK';

    // المدفوعات
    const PAYMENT_RECEIVED = 'PAYMENT_RECEIVED';
    const PAYMENT_FAILED = 'PAYMENT_FAILED';
    const PAYMENT_OVERDUE = 'PAYMENT_OVERDUE';

    // الطلبات
    const NEW_ORDER = 'NEW_ORDER';
    const ORDER_CONFIRMED = 'ORDER_CONFIRMED';
    const ORDER_CANCELLED = 'ORDER_CANCELLED';

    // المواعيد
    const APPOINTMENT_BOOKED = 'APPOINTMENT_BOOKED';
    const APPOINTMENT_CANCELLED = 'APPOINTMENT_CANCELLED';
    const APPOINTMENT_REMINDER = 'APPOINTMENT_REMINDER';

    // المرضى
    const NEW_PATIENT = 'NEW_PATIENT';
    const PATIENT_RETURN = 'PATIENT_RETURN';

    // العلاجات
    const TREATMENT_STARTED = 'TREATMENT_STARTED';
    const TREATMENT_COMPLETED = 'TREATMENT_COMPLETED';

    // قائمة بالأيقونات المناسبة
    public static function getIcon($code)
    {
        return [
            self::LOW_STOCK => '📦',
            self::OUT_OF_STOCK => '🚫',
            self::PAYMENT_RECEIVED => '💰',
            self::PAYMENT_FAILED => '💳',
            self::PAYMENT_OVERDUE => '⏰',
            self::NEW_ORDER => '🛒',
            self::ORDER_CONFIRMED => '✅',
            self::ORDER_CANCELLED => '❌',
            self::APPOINTMENT_BOOKED => '📅',
            self::APPOINTMENT_CANCELLED => '❌',
            self::APPOINTMENT_REMINDER => '⏰',
            self::NEW_PATIENT => '👤',
            self::PATIENT_RETURN => '🔄',
            self::TREATMENT_STARTED => '🔧',
            self::TREATMENT_COMPLETED => '✅',
        ][$code] ?? '🔔';
    }

    // قائمة بالرسائل الافتراضية
    public static function getDefaultMessage($code)
    {
        return [
            self::LOW_STOCK => 'المخزون منخفض',
            self::OUT_OF_STOCK => 'نفذ المخزون',
            self::PAYMENT_RECEIVED => 'تم استلام دفعة',
            self::PAYMENT_FAILED => 'فشلت عملية الدفع',
            self::PAYMENT_OVERDUE => 'دفعة متأخرة',
            self::NEW_ORDER => 'طلب جديد',
            self::ORDER_CONFIRMED => 'تم تأكيد الطلب',
            self::ORDER_CANCELLED => 'تم إلغاء الطلب',
            self::APPOINTMENT_BOOKED => 'تم حجز موعد',
            self::APPOINTMENT_CANCELLED => 'تم إلغاء موعد',
            self::APPOINTMENT_REMINDER => 'تذكير بموعد',
            self::NEW_PATIENT => 'مريض جديد',
            self::PATIENT_RETURN => 'عودة مريض',
            self::TREATMENT_STARTED => 'بدء علاج',
            self::TREATMENT_COMPLETED => 'اكتمال علاج',
        ][$code] ?? 'إشعار جديد';
    }
}
