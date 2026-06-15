<?php

namespace App\Listeners;

use App\Events\AdminOverride;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class LogAdminOverride
{
    public function handle(AdminOverride $event)
    {
        ActivityLog::create([
            'company_id' => null,
            'branch_id'  => null,
            'user_id'    => $event->adminId,
            'action'     => 'admin.override.' . $event->action,
            'subject_type' => 'System',
            'subject_id'   => 0,
            'properties' => array_merge(
                $event->context,
                [
                    'overridden_at' => now(),
                ]
            ),
        ]);

        Log::info('Admin override', [
            'admin_id' => $event->adminId,
            'action' => $event->action,
            'target_id' => $event->targetId,
        ]);
    }
}
