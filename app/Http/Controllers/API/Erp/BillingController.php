<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\BillingInvoice;
use App\Models\PaymentMethod;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    /**
     * GET /api/erp/billing/invoices
     * فواتير الاشتراك السابقة
     */
    public function invoices()
    {
        $companyId = Tenant::id();

        $invoices = BillingInvoice::where('company_id', $companyId)
            ->latest()
            ->get();

        return response()->json([
            'msg' => 'Billing invoices',
            'status' => 200,
            'data' => $invoices,
        ]);
    }

    /**
     * GET /api/erp/billing/payment-methods
     * وسائل الدفع المحفوظة
     */
    public function paymentMethods()
    {
        $companyId = Tenant::id();

        $methods = PaymentMethod::where('company_id', $companyId)
            ->orderByDesc('is_default')
            ->get();

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
        $isYearly = $request->billing_cycle === 'yearly';

        // حساب السعر
        if ($isYearly) {
            $monthlyPrice = $plan->price_monthly;
            $amount = ($monthlyPrice * 12) * 0.8;
        } else {
            $amount = $plan->price_monthly;
        }

        $endsAt = $isYearly ? now()->addYear() : now()->addMonth();

        return DB::transaction(function () use ($companyId, $plan, $amount, $isYearly, $endsAt) {
            // إلغاء الاشتراك القديم
            Subscription::where('company_id', $companyId)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            // إنشاء اشتراك بحالة pending
            $subscription = Subscription::create([
                'company_id' => $companyId,
                'plan_id' => $plan->id,
                'starts_at' => now(),
                'ends_at' => $endsAt,
                'amount' => $amount,
                'billing_cycle' => $isYearly ? 'yearly' : 'monthly',
                'status' => 'pending',
                'payment_gateway' => 'paymob',
            ]);

            // إنشاء طلب دفع عند PayMob
            $paymob = new \App\Services\PayMobService();
            $intention = $paymob->createIntention([
                'amount' => (int) ($amount * 100), // بالقروش
                'currency' => 'EGP',
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'company_id' => $companyId,
                    'plan_id' => $plan->id,
                ],
            ]);

            // تحديث الاشتراك بـ payment_intent_id
            $subscription->update(['payment_intent_id' => $intention['id']]);

            return response()->json([
                'msg' => 'Payment initiated',
                'status' => 200,
                'payment_url' => $intention['iframe_url'],
                'subscription_id' => $subscription->id,
            ]);
        });
    }

    /**
     * POST /api/erp/billing/payment-methods
     * إضافة وسيلة دفع جديدة
     */
    public function addPaymentMethod(Request $request)
    {
        $request->validate([
            'stripe_token' => ['required', 'string'],
            'is_default' => ['boolean'],
        ]);

        $companyId = Tenant::id();

        // مؤقت - محتاج ربط بـ Stripe فعلي
        $method = PaymentMethod::create([
            'company_id' => $companyId,
            'stripe_id' => 'pm_' . uniqid(),
            'card_brand' => 'Visa',
            'card_last4' => '4242',
            'card_exp_month' => 12,
            'card_exp_year' => 2026,
            'is_default' => $request->is_default ?? true,
        ]);

        return response()->json([
            'msg' => 'Payment method added successfully',
            'status' => 200,
            'data' => $method,
        ]);
    }

    /**
     * DELETE /api/erp/billing/payment-methods/{id}
     * حذف وسيلة دفع
     */
    public function removePaymentMethod($id)
    {
        $companyId = Tenant::id();

        $method = PaymentMethod::where('company_id', $companyId)
            ->where('id', $id)
            ->firstOrFail();

        $method->delete();

        return response()->json([
            'msg' => 'Payment method removed',
            'status' => 200,
        ]);
    }

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

        // ✅ لو فيه اشتراك، رجعه
        if ($subscription) {
            if ($subscription->plan && is_string($subscription->plan->features)) {
                $subscription->plan->features = json_decode($subscription->plan->features, true);
            }

            return response()->json([
                'msg' => 'Current subscription',
                'status' => 200,
                'data' => $subscription,
            ]);
        }

        // ✅ لو مفيش اشتراك، رجع null
        return response()->json([
            'msg' => 'No active subscription',
            'status' => 200,
            'data' => null,
        ]);
    }

    // BillingController.php
    public function cancelPending($id)
    {
        $companyId = Tenant::id();

        Subscription::where('company_id', $companyId)
            ->where('id', $id)
            ->where('status', 'pending')
            ->delete();

        return response()->json(['status' => 'ok']);
    }
}
