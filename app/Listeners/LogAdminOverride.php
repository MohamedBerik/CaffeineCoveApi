<?php

namespace App\Listeners;

use App\Events\AdminOverride;
use App\Events\SecurityFeedUpdated;
use App\Models\SecurityEvent;
use Illuminate\Support\Facades\Log;

class LogAdminOverride
{
    public function handle(AdminOverride $event)
    {
        try {
            SecurityEvent::create([
                'type'    => 'admin_override',
                'title'   => $event->action,
                'user_id' => $event->adminId,
                'ip'      => request()->ip(),
                'payload' => array_merge($event->context, [
                    'target_id'     => $event->targetId,
                    'overridden_at' => now()->toIso8601String(),
                ]),
            ]);

            event(new SecurityFeedUpdated([
                'type'       => 'admin_override',
                'title'      => $event->action,
                'admin_id'   => $event->adminId,
                'target_id'  => $event->targetId,
                'context'    => $event->context,
                'created_at' => now()->toIso8601String(),
            ]));
        } catch (\Exception $e) {
            Log::error('Cannot log admin override: ' . $e->getMessage());
        }
    }
}
