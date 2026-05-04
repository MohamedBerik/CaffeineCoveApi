<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\Tenant;
use Illuminate\Http\Request;

class SetBranchContext
{
    public function handle(Request $request, Closure $next)
    {
        // تعيين branchId من الهيدر X-Branch-ID إن وُجد
        $branchId = $request->header('X-Branch-ID');
        if ($branchId !== null && $branchId !== '' && $branchId !== 'all') {
            Tenant::setBranchId((int) $branchId);
        } elseif ($user = $request->user()) {
            // وإلا استخدم branch_id الخاص بالمستخدم المسجل
            Tenant::setBranchId($user->branch_id);
        } else {
            Tenant::setBranchId(null);
        }

        return $next($request);
    }
}
