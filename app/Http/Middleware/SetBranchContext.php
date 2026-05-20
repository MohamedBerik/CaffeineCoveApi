<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Tenant;

class SetBranchContext
{
    public function handle(Request $request, Closure $next)
    {
        Tenant::setBranchId(null);

        $branchId = $request->header('X-Branch-ID');

        if (
            $branchId !== null &&
            $branchId !== '' &&
            $branchId !== 'all'
        ) {
            Tenant::setBranchId((int) $branchId);
        } elseif ($request->user()) {
            Tenant::setBranchId(
                $request->user()->branch_id
            );
        }

        return $next($request);
    }
}
