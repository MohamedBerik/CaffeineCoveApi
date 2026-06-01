<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use App\Services\Tenant;
use Closure;
use Illuminate\Http\Request;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // ✅ Super Admin يعدي كل حاجة
        if ($user->is_super_admin) {
            return $next($request);
        }

        $companyId = Tenant::id();

        if (!$companyId) {
            return $next($request);
        }

        // ✅ Company Admin - فحص الاشتراك
        $subscription = Subscription::where('company_id', $companyId)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        // ✅ لو مفيش اشتراك نشط
        if (!$subscription) {
            // لو Trial - مسموح
            $company = \App\Models\Company::find($companyId);
            if ($company && $company->status === 'trial') {
                return $next($request);
            }

            // ✅ مسموح بـ Billing Routes فقط
            if ($request->is('api/erp/billing*')) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Your subscription is not active. Please subscribe to continue.',
                'code' => 'SUBSCRIPTION_INACTIVE',
                'redirect_to' => '/admin/erp/billing',
            ], 403);
        }

        // ✅ اشتراك منتهي
        if ($subscription->ends_at && now()->gt($subscription->ends_at)) {
            // ✅ مسموح بـ Billing Routes فقط
            if ($request->is('api/erp/billing*')) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Your subscription has expired. Please renew to continue.',
                'code' => 'SUBSCRIPTION_EXPIRED',
                'redirect_to' => '/admin/erp/billing',
            ], 403);
        }

        // ✅ اشتراك متأخر (past_due)
        if ($subscription->status === 'past_due') {
            return response()->json([
                'message' => 'Your payment is past due. Please update your payment method.',
                'code' => 'SUBSCRIPTION_PAST_DUE',
                'redirect_to' => '/admin/erp/billing',
            ], 403);
        }

        return $next($request);
    }
}
