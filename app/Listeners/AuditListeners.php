<?php

namespace App\Listeners;

use App\Events\FailedLogin;
use App\Events\SuspiciousActivity;
use App\Events\AdminOverride;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class LogFailedLogin
{
    public function handle(FailedLogin $event)
    {
        try {
            ActivityLog::create([]);
        } catch (\Exception $e) {
            Log::error('Cannot log failed login: ' . $e->getMessage());
        }

        Log::warning('Failed login attempt', []);
    }
}

class LogSuspiciousActivity
{
    public function handle(SuspiciousActivity $event)
    {
        ActivityLog::create([
            'company_id' => null,
            'user_id' => $event->userId,
            'action' => 'suspicious.' . $event->action,
            'subject_type' => 'System',
            'subject_id' => null,
            'properties' => array_merge($event->context, [
                'detected_at' => now(),
            ]),
        ]);

        Log::warning('Suspicious activity detected', [
            'user_id' => $event->userId,
            'action' => $event->action,
            'context' => $event->context,
        ]);
    }
}

class LogAdminOverride
{
    public function handle(AdminOverride $event)
    {
        ActivityLog::create([
            'company_id' => null,
            'user_id' => $event->adminId,
            'action' => 'admin.override.' . $event->action,
            'subject_type' => 'System',
            'subject_id' => $event->targetId,
            'properties' => array_merge($event->context, [
                'overridden_at' => now(),
            ]),
        ]);

        Log::info('Admin override', [
            'admin_id' => $event->adminId,
            'action' => $event->action,
            'target_id' => $event->targetId,
        ]);
    }
}
