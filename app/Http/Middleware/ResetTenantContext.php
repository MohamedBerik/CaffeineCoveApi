<?php

namespace App\Http\Middleware;

use App\Services\Tenant;

class ResetTenantContext
{
    public function handle($job, $next)
    {
        try {
            return $next($job);
        } finally {
            Tenant::reset();
        }
    }
}
