<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// ✅ القناة الشخصية للمستخدم
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// ✅ قناة خاصة للداشبورد (تتطلب صلاحية)
Broadcast::channel('company.{companyId}.dashboard', function ($user, $companyId) {
    // Super admin يسمع كل الشركات
    if ($user->is_super_admin) {
        return true;
    }

    // مستخدم عادي يسمع شركته فقط
    return (int) $user->company_id === (int) $companyId;
});

// ✅ قناة خاصة للتنبيهات (Alerts)
Broadcast::channel('company.{companyId}.alerts', function ($user, $companyId) {
    if ($user->is_super_admin) {
        return true;
    }

    return (int) $user->company_id === (int) $companyId;
});

// ✅ قناة خاصة للـ Insights
Broadcast::channel('company.{companyId}.insights', function ($user, $companyId) {
    if ($user->is_super_admin) {
        return true;
    }

    return (int) $user->company_id === (int) $companyId;
});

// ✅ قناة خاصة للإشعارات (Notifications)
Broadcast::channel('company.{companyId}.notifications', function ($user, $companyId) {
    if ($user->is_super_admin) {
        return true;
    }

    return (int) $user->company_id === (int) $companyId;
});

// ✅ قناة خاصة للمواعيد (للمرضى)
Broadcast::channel('appointments.{patientId}', function ($user, $patientId) {
    // Admin و Super admin يسمعوا كل المواعيد
    if ($user->is_super_admin || $user->role === 'admin') {
        return true;
    }

    // المريض يسمع مواعيده فقط
    $patient = \App\Models\Customer::where('email', $user->email)->first();
    return $patient && (int) $patient->id === (int) $patientId;
});

// ✅ قناة خاصة بالدكتور
Broadcast::channel('doctor.{doctorId}', function ($user, $doctorId) {
    if ($user->is_super_admin) {
        return true;
    }

    // Admin يسمع دكاترة شركته
    if ($user->role === 'admin') {
        $doctor = \App\Models\Doctor::find($doctorId);
        return $doctor && $doctor->company_id === $user->company_id;
    }

    // الدكتور يسمع نفسه فقط
    $doctor = \App\Models\Doctor::where('email', $user->email)->first();
    return $doctor && (int) $doctor->id === (int) $doctorId;
});
