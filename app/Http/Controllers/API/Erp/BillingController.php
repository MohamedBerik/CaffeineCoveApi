<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    /**
     * GET /api/erp/billing/subscription
     * الاشتراك الحالي للشركة
     */
    public function currentSubscription()
    {
        $companyId = Tenant::id();

        $subscription = Subscription::where('company_id', $companyId)
            ->with('plan')
            ->latest()
            ->first();

        // لو مفيش اشتراك، نرجع بيانات افتراضية (Trial)
        if (!$subscription) {
            $company = \App\Models\Company::find($companyId);
            $subscription = [
                'id' => null,
                'status' => $company->status ?? 'trial',
                'starts_at' => $company->created_at,
                'ends_at' => $company->trial_ends_at,
                'amount' => 0,
                'plan' => [
                    'id' => null,
                    'name' => 'Trial Plan',
                    'name_ar' => 'الخطة التجريبية',
                ],
            ];
        }

        return response()->json([
            'msg' => 'Current subscription',
            'status' => 200,
            'data' => $subscription,
        ]);
    }

    /**
     * GET /api/erp/billing/invoices
     * فواتير الاشتراك السابقة
     */
    public function invoices()
    {
        $companyId = Tenant::id();

        // لو مفيش جدول billing_invoices، نرجع بيانات وهمية
        $invoices = [
            [
                'id' => 1,
                'number' => 'INV-2024-001',
                'amount' => 199,
                'status' => 'paid',
                'created_at' => now()->subMonths(1),
            ],
            [
                'id' => 2,
                'number' => 'INV-2024-002',
                'amount' => 199,
                'status' => 'paid',
                'created_at' => now(),
            ],
        ];

        return response()->json([
            'msg' => 'Billing invoices',
            'status' => 200,
            'data' => $invoices,
        ]);
    }

    /**
     * GET /api/erp/billing/plans
     * الخطط المتاحة للاشتراك
     */
    public function availablePlans()
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('price_monthly')
            ->get();

        return response()->json([
            'msg' => 'Available plans',
            'status' => 200,
            'data' => $plans,
        ]);
    }

    /**
     * GET /api/erp/billing/payment-methods
     * وسائل الدفع المحفوظة
     */
    public function paymentMethods()
    {
        // مؤقت - لو مفيش جدول payment_methods
        $methods = [
            [
                'id' => 1,
                'card_brand' => 'Visa',
                'card_last4' => '4242',
                'card_exp_month' => 12,
                'card_exp_year' => 2026,
                'is_default' => true,
            ],
        ];

        return response()->json([
            'msg' => 'Payment methods',
            'status' => 200,
            'data' => $methods,
        ]);
    }

    /**
     * POST /api/erp/billing/subscribe
     * الاشتراك في خطة جديدة
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $companyId = Tenant::id();
        $plan = Plan::findOrFail($request->plan_id);

        $amount = $request->billing_cycle === 'monthly'
            ? $plan->price_monthly
            : ($plan->price_yearly ?? $plan->price_monthly * 10);

        return DB::transaction(function () use ($companyId, $plan, $amount) {
            // إلغاء الاشتراك القديم
            Subscription::where('company_id', $companyId)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            // إنشاء اشتراك جديد
            $subscription = Subscription::create([
                'company_id' => $companyId,
                'plan_id' => $plan->id,
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
                'amount' => $amount,
                'status' => 'active',
            ]);

            // تحديث حالة الشركة
            \App\Models\Company::where('id', $companyId)
                ->update(['status' => 'active']);

            return response()->json([
                'msg' => 'Subscription activated successfully',
                'status' => 200,
                'data' => $subscription,
            ]);
        });
    }

    /**
     * POST /api/erp/billing/cancel
     * إلغاء الاشتراك
     */
    public function cancel()
    {
        $companyId = Tenant::id();

        Subscription::where('company_id', $companyId)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        return response()->json([
            'msg' => 'Subscription cancelled successfully',
            'status' => 200,
        ]);
    }

    /**
     * POST /api/erp/billing/payment-methods
     * إضافة وسيلة دفع جديدة
     */
    public function addPaymentMethod(Request $request)
    {
        // مؤقت - محتاج ربط بـ Stripe/PayMob
        return response()->json([
            'msg' => 'Payment method added successfully',
            'status' => 200,
            'data' => [
                'id' => rand(1, 100),
                'card_brand' => 'Visa',
                'card_last4' => substr($request->card_number ?? '4242424242424242', -4),
                'card_exp_month' => $request->exp_month ?? 12,
                'card_exp_year' => $request->exp_year ?? 2026,
                'is_default' => true,
            ],
        ]);
    }

    /**
     * DELETE /api/erp/billing/payment-methods/{id}
     * حذف وسيلة دفع
     */
    public function removePaymentMethod($id)
    {
        return response()->json([
            'msg' => 'Payment method removed',
            'status' => 200,
        ]);
    }
}
