<?php

namespace App\Providers;

use App\Services\Tenant;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ✅ إضافة middleware 'branch.context' (أو 'company.user' إذا لم ينشأ بعد)
        Broadcast::routes([
            'middleware' => ['api', 'auth:sanctum', 'company.user', 'throttle:broadcasting']
            // يمكن إضافة 'branch.context' إذا كان ميدلوير الفرع جاهزًا
        ]);

        require base_path('routes/channels.php');

        $this->registerTenantChannelRules();
    }

    protected function registerTenantChannelRules(): void
    {
        // ---- قنوات عامة على مستوى الشركة ----
        Broadcast::channel('company.{companyId}', function ($user, $companyId) {
            if ($user->isSuperAdmin()) return true;
            return (int) $user->company_id === (int) $companyId;
        });

        Broadcast::channel('user.{userId}', function ($user, $userId) {
            return (int) $user->id === (int) $userId;
        });

        Broadcast::channel('doctor.{doctorId}', function ($user, $doctorId) {
            if ($user->isSuperAdmin()) return true;
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

        // ---- قنوات الإشعارات والأحداث العامة (لجميع الفروع) ----
        Broadcast::channel('company.{companyId}.notifications', function ($user, $companyId) {
            if ($user->isSuperAdmin()) return true;
            return (int) $user->company_id === (int) $companyId;
        });

        // ---- قنوات Branch-Scoped (تحل محل القنوات العامة القديمة) ----
        Broadcast::channel('company.{companyId}.branch.{branchId}.dashboard', function ($user, $companyId, $branchId) {
            if ($user->isSuperAdmin()) return true;
            if ((int) $user->company_id !== (int) $companyId) return false;
            // الأدمن (role = admin) يمكنه رؤية جميع الفروع
            if ($user->role === 'admin') return true;
            // المستخدم العادي (طبيب/موظف) يجب أن يكون مرتبطًا بنفس الفرع
            return $user->branch_id && (int) $user->branch_id === (int) $branchId;
        });

        Broadcast::channel('company.{companyId}.branch.{branchId}.alerts', function ($user, $companyId, $branchId) {
            if ($user->isSuperAdmin()) return true;
            if ((int) $user->company_id !== (int) $companyId) return false;
            if ($user->role === 'admin') return true;
            return $user->branch_id && (int) $user->branch_id === (int) $branchId;
        });

        Broadcast::channel('company.{companyId}.branch.{branchId}.insights', function ($user, $companyId, $branchId) {
            if ($user->isSuperAdmin()) return true;
            if ((int) $user->company_id !== (int) $companyId) return false;
            if ($user->role === 'admin') return true;
            return $user->branch_id && (int) $user->branch_id === (int) $branchId;
        });

        // يمكن إضافة قنوات إضافية بنفس النمط
    }
}
