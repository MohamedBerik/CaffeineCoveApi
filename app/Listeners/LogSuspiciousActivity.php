<?php

namespace App\Listeners;

use App\Events\SecurityFeedUpdated;
use App\Events\SuspiciousActivity;
use App\Models\SecurityEvent;
use Illuminate\Support\Facades\Log;

class LogSuspiciousActivity
{
    public function handle(SuspiciousActivity $event)
    {
        SecurityEvent::create([
            'type'    => 'suspicious_activity',
            'title'   => $event->action,
            'user_id' => $event->userId,
            'ip'      => $event->context['ip'] ?? null,
            'payload' => array_merge($event->context, [
                'detected_at' => now()->toIso8601String(),
            ]),
        ]);

        event(new SecurityFeedUpdated([
            'type'       => 'suspicious',
            'title'      => $event->action,
            'user_id'    => $event->userId,
            'context'    => $event->context,
            'created_at' => now()->toIso8601String(),
        ]));

        Log::warning('Suspicious activity detected', [
            'user_id' => $event->userId,
            'action'  => $event->action,
            'context' => $event->context,
        ]);
    }
}
