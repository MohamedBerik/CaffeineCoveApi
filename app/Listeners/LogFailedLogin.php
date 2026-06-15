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
            // 🔍 محاولة جلب المستخدم لمعرفة الـ ID الحقيقي له إن وجد
            $user = \App\Models\User::withoutGlobalScopes()
                ->where('email', $event->email)
                ->first();

            ActivityLog::withoutGlobalScopes()->create([
                'company_id'   => Tenant::id() ?? 1,
                'branch_id'    => null,
                'user_id'      => null, // يترك فارغاً لأنه لم يسجل دخول بنجاح

                'action'       => 'auth.failed_login',
                'subject_type' => 'User',

                // 🌟 الحل: إذا وجدنا المستخدم نضع معرفه، وإذا لم نجده نضع -1 (لتجنب فخ الصفر والـ null)
                'subject_id'   => $user ? $user->id : -1,

                'properties'   => [
                    'email'        => $event->email,
                    'ip'           => $event->ip,
                    'reason'       => $event->reason,
                    'attempted_at' => now(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Cannot log failed login: ' . $e->getMessage());
        }

        Log::warning('Failed login attempt', [
            'email'  => $event->email,
            'ip'     => $event->ip,
            'reason' => $event->reason,
        ]);
    }
}
