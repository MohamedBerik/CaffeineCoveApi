<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\Tenant;
use Illuminate\Http\Request;

class SetBranchContext
{
    public function handle(Request $request, Closure $next)
    {
        $branchId = $request->header('X-Branch-ID');

        if ($branchId !== null && $branchId !== '' && $branchId !== 'all') {
            Tenant::setBranchId((int) $branchId);
        } elseif ($user = $request->user()) {
            Tenant::setBranchId($user->branch_id);
        }

        try {
            return $next($request);
        } finally {
            // IMPORTANT
            Tenant::setBranchId(null);
        }
    }
}
