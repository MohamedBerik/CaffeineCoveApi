<?php

namespace App\Listeners;

use App\Events\SecurityFeedUpdated;
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

        event(
            new SecurityFeedUpdated([
                'type' => 'suspicious',
                'title' => $event->action,
                'user_id' => $event->userId,
                'context' => $event->context,
                'created_at' => now()->toIso8601String(),
            ])
        );

        Log::warning('Suspicious activity detected', [
            'user_id' => $event->userId,
            'action' => $event->action,
            'context' => $event->context,
        ]);
    }
}
