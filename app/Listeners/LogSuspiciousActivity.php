<?php

namespace App\Listeners;

use App\Events\SuspiciousActivity;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class LogSuspiciousActivity
{
    public function handle(SuspiciousActivity $event)
    {
        ActivityLog::create([
            'company_id' => null,
            'branch_id'  => null,
            'user_id'    => $event->userId,
            'action'     => 'suspicious.' . $event->action,
            'subject_type' => 'System',
            'subject_id'   => 0,
            'properties' => array_merge(
                $event->context,
                [
                    'detected_at' => now(),
                ]
            ),
        ]);

        Log::warning('Suspicious activity detected', [
            'user_id' => $event->userId,
            'action' => $event->action,
            'context' => $event->context,
        ]);
    }
}
