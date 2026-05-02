<?php

namespace App\Providers;

use App\Services\Tenant;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ✅ إضافة middleware 'api' لتطبيق CORS
        Broadcast::routes(['middleware' => ['api', 'auth:sanctum']]);

        require base_path('routes/channels.php');

        $this->registerTenantChannelRules();
    }

    protected function registerTenantChannelRules(): void
    {
        Broadcast::channel('company.{companyId}', function ($user, $companyId) {
            if ($user->isSuperAdmin()) {
                return true;
            }
            return (int) $user->company_id === (int) $companyId;
        });

        Broadcast::channel('user.{userId}', function ($user, $userId) {
            return (int) $user->id === (int) $userId;
        });

        Broadcast::channel('doctor.{doctorId}', function ($user, $doctorId) {
            if ($user->isSuperAdmin()) {
                return true;
            }
            if ($user->role === 'admin') {
                $doctor = \App\Models\Doctor::find($doctorId);
                return $doctor && $doctor->company_id === $user->company_id;
            }
            if ($user->role === 'doctor') {
                $doctor = \App\Models\Doctor::where('email', $user->email)->first();
                return $doctor && (int) $doctor->id === (int) $doctorId;
            }
            return false;
        });

        Broadcast::channel('appointments.{patientId}', function ($user, $patientId) {
            if ($user->role === 'user') {
                $patient = \App\Models\Customer::where('email', $user->email)->first();
                return $patient && (int) $patient->id === (int) $patientId;
            }
            return $user->isSuperAdmin() || $user->role === 'admin';
        });

        Broadcast::channel('company.{companyId}.notifications', function ($user, $companyId) {
            if ($user->isSuperAdmin()) {
                return true;
            }
            return (int) $user->company_id === (int) $companyId;
        });

        Broadcast::channel('company.{companyId}.dashboard', function ($user, $companyId) {
            if ($user->isSuperAdmin()) {
                return true;
            }
            return (int) $user->company_id === (int) $companyId && $user->role === 'admin';
        });

        Broadcast::channel('company.{companyId}.alerts', function ($user, $companyId) {
            if ($user->isSuperAdmin()) {
                return true;
            }
            return (int) $user->company_id === (int) $companyId &&
                in_array($user->role, ['admin', 'doctor']);
        });
    }
}
