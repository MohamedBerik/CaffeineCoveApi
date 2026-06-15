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

            ActivityLog::withoutGlobalScopes()->create([
                'company_id'   => Tenant::id() ?? 1,
                'branch_id'    => null,
                'user_id'      => null,

                'action'       => 'auth.failed_login',

                'subject_type' => 'User',

                // مهم جداً
                'subject_id'   => 0,

                'properties'   => [
                    'email'        => $event->email,
                    'ip'           => $event->ip,
                    'reason'       => $event->reason,
                    'attempted_at' => now(),
                ],
            ]);
        } catch (\Exception $e) {

            Log::error(
                'Cannot log failed login: ' .
                    $e->getMessage()
            );
        }

        Log::warning('Failed login attempt', [
            'email' => $event->email,
            'ip' => $event->ip,
            'reason' => $event->reason,
        ]);
    }
}
