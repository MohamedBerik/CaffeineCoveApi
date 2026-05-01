<?php

namespace App\Listeners;

use App\Events\FailedLogin;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class LogFailedLogin
{
    public function handle(FailedLogin $event)
    {
        try {
            ActivityLog::create([
                'company_id' => null,
                'user_id' => null,
                'action' => 'auth.failed_login',
                'subject_type' => 'User',
                'subject_id' => null,
                'properties' => [
                    'email' => $event->email,
                    'ip' => $event->ip,
                    'reason' => $event->reason,
                    'attempted_at' => now(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Cannot log failed login: ' . $e->getMessage());
        }

        Log::warning('Failed login attempt', [
            'email' => $event->email,
            'ip' => $event->ip,
            'reason' => $event->reason,
        ]);
    }
}
