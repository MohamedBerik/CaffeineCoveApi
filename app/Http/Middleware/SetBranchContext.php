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
        $resolvedBranchId = null;

        if ($branchId !== null && $branchId !== '' && $branchId !== 'all') {
            $resolvedBranchId = (int) $branchId;
        } elseif ($user = $request->user()) {
            // استخدام فرع المستخدم الافتراضي إذا لم يرسل الهيدر
            $resolvedBranchId = $user->branch_id ? (int) $user->branch_id : null;
        }

        // حقن القيمة في الـ Singleton والـ Service Container في نفس الوقت لمنع الـ Inconsistency
        Tenant::setBranchId($resolvedBranchId);

        if ($resolvedBranchId !== null) {
            app()->instance('tenant_branch_id', $resolvedBranchId);
        } else {
            if (app()->has('tenant_branch_id')) {
                // تصفير الحاوية إذا كانت القيمة السابقة موجودة
                app()->offsetUnset('tenant_branch_id');
            }
        }

        return $next($request);
    }
}
