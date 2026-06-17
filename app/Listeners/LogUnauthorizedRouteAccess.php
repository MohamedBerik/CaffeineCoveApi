<?php

namespace App\Listeners;

use App\Events\UnauthorizedRouteAccess;
use App\Models\ActivityLog;
use App\Services\Tenant;
use Illuminate\Support\Facades\Log;

class LogUnauthorizedRouteAccess
{
    public function handle(UnauthorizedRouteAccess $event)
    {
        try {

            ActivityLog::withoutGlobalScopes()->create([
                'company_id' => Tenant::id(),
                'branch_id' => null,
                'user_id' => $event->userId,

                'action' => 'suspicious.unauthorized_route_access',

                'subject_type' => 'System',
                'subject_id' => 0,

                'properties' => [
                    'route' => $event->route,
                    'method' => $event->method,
                    'ip' => $event->ip,
                    'context' => $event->context,
                    'detected_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error(
                'UNAUTHORIZED_ROUTE_ACCESS_FAILED: ' .
                    $e->getMessage()
            );
        }
    }
}
