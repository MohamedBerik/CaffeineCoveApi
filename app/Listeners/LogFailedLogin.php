<?php

namespace App\Listeners;

use App\Events\FailedLogin;
use App\Models\ActivityLog;
use App\Services\Tenant;
use Illuminate\Support\Facades\Log;

class LogFailedLogin
{
    public function handle(FailedLogin $event)
    {
        try {
            Log::info('FAILED LOGIN LISTENER REACHED', [
                'email' => $event->email,
                'tenant' => Tenant::id(),
            ]);

            // 🔍 جلب المستخدم لمعرفة الـ ID الحقيقي له
            $user = \App\Models\User::withoutGlobalScopes()
                ->where('email', $event->email)
                ->first();

            ActivityLog::withoutGlobalScopes()->create([
                'company_id' => $user?->company_id ?? Tenant::id(),
                'branch_id'    => null,
                'user_id'      => null,
                'action'       => 'auth.failed_login',
                'subject_type' => 'User',

                // 🌟 استخدام -1 لتفادي فخ الـ null والـ Zero تماماً
                'subject_id'   => $user ? $user->id : 0,

                'properties'   => [
                    'email'        => $event->email,
                    'ip'           => $event->ip,
                    'reason'       => $event->reason,
                    'attempted_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            // ✅ الصح: سجل الخطأ الأصلي في صمت جوه ملف الـ laravel.log بدون ما توقع السيستم
            Log::error('❌ FAILED_LOGIN_LISTENER_CRASHED: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString() // هيجيبلك المشكلة من جدرها
            ]);
        }

        // لوج تحذيري خارجي
        Log::warning('Failed login attempt logged', [
            'email' => $event->email,
        ]);
    }
}
