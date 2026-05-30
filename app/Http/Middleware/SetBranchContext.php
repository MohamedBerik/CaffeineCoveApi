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

        // 🎯 [تعديل حاسم وآمن] نعتمد بالكامل على الهيدر الصريح القادم من الفرونت إند
        // لمنع استدعاء $request->user() المبكر الذي يسبب الـ 401
        if ($branchId !== null && $branchId !== '' && $branchId !== 'all') {
            $resolvedBranchId = (int) $branchId;
        }

        // حقن القيمة في الـ Singleton والـ Service Container في نفس الوقت لمنع الـ Inconsistency
        Tenant::setBranchId($resolvedBranchId);

        if ($resolvedBranchId !== null) {
            app()->instance('tenant_branch_id', $resolvedBranchId);
        } else {
            // ✅ إذا كانت القيمة null أو "all"، نضمن تصفير الحاوية تماماً ليفهم الـ Global Scope أن المستخدم يرى كل الفروع
            if (app()->has('tenant_branch_id')) {
                app()->offsetUnset('tenant_branch_id');
            }
        }

        return $next($request);
    }
}
