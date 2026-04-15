<?php
// app/Http/Middleware/ResetTenantContext.php

namespace App\Http\Middleware;

use Closure;
use App\Services\Tenant;

class ResetTenantContext
{
    public function handle($request, Closure $next)
    {
        // ✅ Reset أي context قديم قبل كل request
        Tenant::reset();

        return $next($request);
    }
}
