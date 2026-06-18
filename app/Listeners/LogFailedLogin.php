<?php

namespace App\Listeners;

use App\Events\FailedLogin;
use App\Events\SecurityFeedUpdated;
use App\Models\SecurityEvent;
use Illuminate\Support\Facades\Log;

class LogFailedLogin
{
    public function handle(FailedLogin $event)
    {
        try {
            // محاولة العثور على المستخدم لتسجيل user_id الحقيقي
            $user = \App\Models\User::withoutGlobalScopes()
                ->where('email', $event->email)
                ->first();

            SecurityEvent::create([
                'type'    => 'failed_login',
                'title'   => 'Failed login attempt',
                'user_id' => $user?->id,
                'email'   => $event->email,
                'ip'      => $event->ip,
                'payload' => [
                    'reason'       => $event->reason,
                    'attempted_at' => now()->toIso8601String(),
                ],
            ]);

            // بث الحدث للواجهة عبر WebSocket
            event(new SecurityFeedUpdated([
                'type'       => 'failed_login',
                'title'      => 'Failed login attempt',
                'email'      => $event->email,
                'ip'         => $event->ip,
                'created_at' => now()->toIso8601String(),
            ]));
        } catch (\Exception $e) {
            Log::error('Failed to log failed login: ' . $e->getMessage(), [
                'email' => $event->email,
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
        }

        Log::warning('Failed login attempt', [
            'email' => $event->email,
            'ip'    => $event->ip,
        ]);
    }
}
