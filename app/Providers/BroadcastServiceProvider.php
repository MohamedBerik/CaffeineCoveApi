<?php

namespace App\Providers;

use App\Services\Tenant;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ✅ استخدام Sanctum للمصادقة
        Broadcast::routes(['middleware' => ['auth:sanctum']]);

        // ✅ تسجيل الـ Channels
        require base_path('routes/channels.php');

        // ✅ تخصيص الـ Channel Authorization للـ Multi-tenant
        $this->registerTenantChannelRules();
    }

    /**
     * Register tenant-specific channel authorization rules
     */
    protected function registerTenantChannelRules(): void
    {
        // ✅ Channel خاص بالشركة
        Broadcast::channel('company.{companyId}', function ($user, $companyId) {
            // Super admin يسمع كل الشركات
            if ($user->isSuperAdmin()) {
                return true;
            }

            // مستخدم عادي يسمع شركته فقط
            return (int) $user->company_id === (int) $companyId;
        });

        // ✅ Channel خاص بالمستخدم
        Broadcast::channel('user.{userId}', function ($user, $userId) {
            return (int) $user->id === (int) $userId;
        });

        // ✅ Channel خاص بالدكتور
        Broadcast::channel('doctor.{doctorId}', function ($user, $doctorId) {
            // Super admin يسمع كل الدكاترة
            if ($user->isSuperAdmin()) {
                return true;
            }

            // Admin يسمع دكاترة شركته
            if ($user->role === 'admin') {
                $doctor = \App\Models\Doctor::find($doctorId);
                return $doctor && $doctor->company_id === $user->company_id;
            }

            // الدكتور يسمع نفسه فقط
            if ($user->role === 'doctor') {
                $doctor = \App\Models\Doctor::where('email', $user->email)->first();
                return $doctor && (int) $doctor->id === (int) $doctorId;
            }

            return false;
        });

        // ✅ Channel للمواعيد (المرضى يسمعوا مواعيدهم)
        Broadcast::channel('appointments.{patientId}', function ($user, $patientId) {
            // المريض يسمع مواعيده فقط
            if ($user->role === 'user') {
                $patient = \App\Models\Customer::where('email', $user->email)->first();
                return $patient && (int) $patient->id === (int) $patientId;
            }

            // Super admin و admin يسمعوا كل المواعيد
            return $user->isSuperAdmin() || $user->role === 'admin';
        });

        // ✅ Channel للإشعارات العامة للشركة
        Broadcast::channel('company.{companyId}.notifications', function ($user, $companyId) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            return (int) $user->company_id === (int) $companyId;
        });

        // ✅ Channel للـ Dashboard updates
        Broadcast::channel('company.{companyId}.dashboard', function ($user, $companyId) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            return (int) $user->company_id === (int) $companyId && $user->role === 'admin';
        });

        // ✅ Channel للـ Alerts
        Broadcast::channel('company.{companyId}.alerts', function ($user, $companyId) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            return (int) $user->company_id === (int) $companyId &&
                in_array($user->role, ['admin', 'doctor']);
        });
    }
}
