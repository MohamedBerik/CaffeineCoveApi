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
    if ($user->is_super_admin) {
        return true;
    }
    return (int) $user->company_id === (int) $companyId;
});


// 🚀 [الجديدة] قناة خاصة لتنبيهات الفروع المحددة (تم إضافتها لحل خطأ الـ 500 للفرع)
Broadcast::channel('company.{companyId}.branch.{branchId}.alerts', function ($user, $companyId, $branchId) {
    if ($user->is_super_admin) {
        return true;
    }

    // التأكد من تطابق الشركة أولاً
    if ((int) $user->company_id !== (int) $companyId) {
        return false;
    }

    // الأدمن يملك صلاحية دخول كل فروع شركته
    if ($user->role === 'admin') {
        return true;
    }

    // الموظف العادي: يجب أن يتطابق فرعه الحالي مع فرع القناة
    return !is_null($user->branch_id) && (int) $user->branch_id === (int) $branchId;
});


// ✅ قناة خاصة للتنبيهات العامة للشركة (عند اختيار All Branches)
Broadcast::channel('company.{companyId}.alerts', function ($user, $companyId) {
    if ($user->is_super_admin) {
        return true;
    }

    if ((int) $user->company_id !== (int) $companyId) {
        return false;
    }

    // يسمح للأدمن بمتابعة قنوات الشركة العامة
    return $user->role === 'admin';
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
    if ($user->is_super_admin || $user->role === 'admin') {
        return true;
    }

    $patient = \App\Models\Customer::where('email', $user->email)->first();
    return $patient && (int) $patient->id === (int) $patientId;
});

// ✅ قناة خاصة بالدكتور
Broadcast::channel('doctor.{doctorId}', function ($user, $doctorId) {
    if ($user->is_super_admin) {
        return true;
    }

    if ($user->role === 'admin') {
        $doctor = \App\Models\Doctor::find($doctorId);
        return $doctor && (int) $doctor->company_id === (int) $user->company_id;
    }

    $doctor = \App\Models\Doctor::where('email', $user->email)->first();
    return $doctor && (int) $doctor->id === (int) $doctorId;
});
