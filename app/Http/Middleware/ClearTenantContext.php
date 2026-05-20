<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\Tenant;

class ClearTenantContext
{
    public function handle($request, Closure $next)
    {
        try {
            return $next($request);
        } finally {
            Tenant::reset();
        }
    }
}
