<?php

namespace App\Listeners;

use App\Events\AdminOverride;
use App\Events\SecurityFeedUpdated;
use App\Models\ActivityLog;
use App\Services\Tenant;
use Illuminate\Support\Facades\Log;

class LogAdminOverride
{
    public function handle(AdminOverride $event)
    {
        try {

            ActivityLog::withoutGlobalScopes()->create([
                'company_id' => Tenant::id() ?? 1,
                'branch_id' => null,
                'user_id' => $event->adminId,

                'action' => 'admin.override.' . $event->action,

                'subject_type' => 'System',
                'subject_id' => $event->targetId ?? 0,

                'properties' => array_merge(
                    $event->context,
                    [
                        'admin_id' => $event->adminId,
                        'overridden_at' => now()->toIso8601String(),
                    ]
                ),
            ]);

            event(
                new SecurityFeedUpdated([
                    'type' => 'admin_override',
                    'title' => $event->action,
                    'admin_id' => $event->adminId,
                    'target_id' => $event->targetId,
                    'context' => $event->context,
                    'created_at' => now()->toIso8601String(),
                ])
            );
        } catch (\Exception $e) {
            Log::error(
                'Cannot log admin override: ' .
                    $e->getMessage()
            );
        }
    }
}
