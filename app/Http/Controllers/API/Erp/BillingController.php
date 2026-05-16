<?php

namespace App\Http\Controllers\API\Erp;

use App\Exceptions\BillingException;
use App\Exceptions\SubscriptionException;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\BillingInvoice;
use App\Models\PaymentMethod;
use App\Services\Tenant;
use App\Services\PayMobService;
use App\Services\ProrationService;
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

        // ✅ التحقق من الخطة (exists validation كفاية)
        $plan = Plan::find($request->plan_id);
        if (!$plan) {
            // ✅ Audit Logging
            event(new \App\Events\SuspiciousActivity(
                auth()->id(),
                'invalid_plan_access',
                ['plan_id' => $request->plan_id, 'company_id' => Tenant::id()]
            ));
            throw new BillingException('Plan not found', 404, 'PLAN_NOT_FOUND');
        }

        // ✅ التحقق من الاشتراك الحالي
        $existingSubscription = Subscription::where('company_id', $companyId)
            ->where('status', 'active')
            ->first();

        if ($existingSubscription) {
            throw new SubscriptionException(
                'You already have an active subscription. Use change plan instead.',
                422,
                'ACTIVE_SUBSCRIPTION_EXISTS'
            );
        }

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

            event(new \App\Events\SubscriptionCreated($subscription));

            // إنشاء طلب دفع عند PayMob
            $paymob = new PayMobService();
            $intention = $paymob->createIntention([
                'amount' => (int) ($amount * 100),
                'currency' => 'EGP',
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'company_id' => $companyId,
                    'plan_id' => $plan->id,
                ],
            ]);

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
     * POST /api/erp/billing/cancel
     * إلغاء الاشتراك الحالي
     */
    public function cancel()
    {
        $companyId = Tenant::id();

        $subscription = Subscription::where('company_id', $companyId)
            ->where('status', 'active')
            ->first();

        if ($subscription) {
            $subscription->update(['status' => 'cancelled']);

            event(new \App\Events\SubscriptionCancelled($subscription));

            return response()->json([
                'msg' => 'Subscription cancelled successfully',
                'status' => 200,
            ]);
        }

        return response()->json([
            'msg' => 'No active subscription found',
            'status' => 404,
        ], 404);
    }

    /**
     * POST /api/erp/billing/cancel-pending/{id}
     * إلغاء اشتراك معلق
     */
    public function cancelPending($id)
    {
        $companyId = Tenant::id();

        Subscription::where('company_id', $companyId)
            ->where('id', $id)
            ->where('status', 'pending')
            ->delete();

        return response()->json(['status' => 'ok']);
    }

    /**
     * POST /api/erp/billing/payment-methods
     * إضافة وسيلة دفع جديدة
     */
    public function addPaymentMethod(Request $request)
    {
        $request->validate([
            'card_brand' => ['required', 'string'],
            'card_last4' => ['required', 'string'],
            'card_exp_month' => ['required', 'integer'],
            'card_exp_year' => ['required', 'integer'],
            'is_default' => ['boolean'],
        ]);

        $companyId = Tenant::id();

        $method = PaymentMethod::create([
            'company_id' => $companyId,
            'gateway' => 'paymob',
            'token' => 'pm_' . uniqid(),
            'card_brand' => $request->card_brand,
            'card_last4' => $request->card_last4,
            'card_exp_month' => $request->card_exp_month,
            'card_exp_year' => $request->card_exp_year,
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
        $company = \App\Models\Company::find($companyId);

        // ✅ تحسين: إرجاع معلومات التجربة إذا لم يوجد اشتراك
        if (!$subscription && $company && $company->status === 'trial') {
            return response()->json([
                'msg' => 'Trial period',
                'status' => 200,
                'data' => [
                    'type' => 'trial',
                    'days_left' => $company->trial_ends_at
                        ? max(0, now()->diffInDays($company->trial_ends_at, false))
                        : null,
                    'trial_ends_at' => $company->trial_ends_at,
                    'plan' => null,
                ]
            ]);
        }

        if ($subscription) {
            // ✅ تأكد إن features راجعة كـ Array
            if ($subscription->plan && is_string($subscription->plan->features)) {
                $subscription->plan->features = json_decode($subscription->plan->features, true);
            }

            return response()->json([
                'msg' => 'Current subscription',
                'status' => 200,
                'data' => $subscription,
            ]);
        }

        return response()->json([
            'msg' => 'No active subscription',
            'status' => 200,
            'data' => null,
        ]);
    }

    /**
     * GET /api/erp/billing/plans
     * الخطط المتاحة
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
     * POST /api/erp/billing/change
     * ترقية/تخفيض الاشتراك مع Proration
     */
    public function change(Request $request, \App\Services\ProrationService $proration)
    {
        $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $companyId = Tenant::id();

        $newPlan = Plan::find($request->plan_id);
        if (!$newPlan) {
            throw new BillingException('Plan not found', 404, 'PLAN_NOT_FOUND');
        }

        $currentSubscription = Subscription::where('company_id', $companyId)
            ->where('status', 'active')
            ->first();

        if (!$currentSubscription) {
            throw new SubscriptionException(
                'No active subscription to change',
                404,
                'NO_ACTIVE_SUBSCRIPTION'
            );
        }

        // ✅ منع التغيير لنفس الخطة ونفس الدورة
        if (
            $currentSubscription->plan_id == $newPlan->id &&
            $currentSubscription->billing_cycle === $request->billing_cycle
        ) {
            throw new SubscriptionException(
                'You are already on this plan with this billing cycle',
                422,
                'SAME_PLAN'
            );
        }

        // ✅ حساب Proration
        $prorationResult = $proration->calculate($currentSubscription, $newPlan);

        $isYearly = $request->billing_cycle === 'yearly';
        if ($isYearly) {
            $newAmount = ($newPlan->price_monthly * 12) * 0.8;
        } else {
            $newAmount = $newPlan->price_monthly;
        }

        $endsAt = $isYearly ? now()->addYear() : now()->addMonth();

        // ✅ لو الترقية تتطلب دفع إضافي
        if ($prorationResult['type'] === 'charge' && $prorationResult['amount'] > 0) {
            return DB::transaction(function () use ($companyId, $currentSubscription, $newPlan, $newAmount, $isYearly, $endsAt, $prorationResult) {

                // إنشاء اشتراك جديد معلق
                $subscription = Subscription::create([
                    'company_id' => $companyId,
                    'plan_id' => $newPlan->id,
                    'starts_at' => now(),
                    'ends_at' => $endsAt,
                    'amount' => $newAmount,
                    'billing_cycle' => $isYearly ? 'yearly' : 'monthly',
                    'status' => 'pending',
                    'payment_gateway' => $currentSubscription->payment_gateway,
                ]);

                // إنشاء فاتورة بالفرق
                $invoice = BillingInvoice::create([
                    'company_id' => $companyId,
                    'subscription_id' => $subscription->id,
                    'number' => BillingInvoice::generateNumber(),
                    'amount' => $prorationResult['amount'],
                    'tax' => 0,
                    'total' => $prorationResult['amount'],
                    'status' => 'pending',
                    'due_date' => now()->addDays(3),
                    'payment_method' => 'card',
                ]);

                // إنشاء نية دفع عند PayMob للفرق
                $paymob = new PayMobService();
                $intention = $paymob->createIntention([
                    'amount' => (int) ($prorationResult['amount'] * 100),
                    'currency' => 'EGP',
                    'metadata' => [
                        'subscription_id' => $subscription->id,
                        'company_id' => $companyId,
                        'plan_id' => $newPlan->id,
                        'type' => 'proration_charge',
                    ],
                ]);

                $subscription->update(['payment_intent_id' => $intention['id']]);

                return response()->json([
                    'msg' => 'Payment required for plan upgrade',
                    'status' => 200,
                    'proration' => $prorationResult,
                    'payment_url' => $intention['iframe_url'],
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                ]);
            });
        }

        // ✅ لو تخفيض (Credit) أو نفس السعر، نغير مباشرة
        return DB::transaction(function () use ($currentSubscription, $newPlan, $newAmount, $isYearly, $endsAt, $prorationResult) {
            $oldPlanId = $currentSubscription->plan_id;
            $oldAmount = $currentSubscription->amount;

            $currentSubscription->update(['status' => 'changed']);

            $subscription = Subscription::create([
                'company_id' => $currentSubscription->company_id,
                'plan_id' => $newPlan->id,
                'starts_at' => now(),
                'ends_at' => $endsAt,
                'amount' => $newAmount,
                'billing_cycle' => $isYearly ? 'yearly' : 'monthly',
                'status' => 'active',
                'payment_gateway' => $currentSubscription->payment_gateway,
            ]);

            event(new \App\Events\SubscriptionCreated($subscription));
            event(new \App\Events\SubscriptionChanged($currentSubscription, $subscription));

            return response()->json([
                'msg' => 'Plan ' . ($prorationResult['type'] === 'credit' ? 'downgraded' : 'changed') . ' successfully',
                'status' => 200,
                'data' => $subscription,
                'proration' => $prorationResult,
                'old_plan_id' => $oldPlanId,
                'new_plan_id' => $newPlan->id,
            ]);
        });
    }

    /**
     * GET /api/erp/billing/status
     * حالة الاشتراك الحالية للشركة
     */
    public function status()
    {
        $companyId = Tenant::id();

        $subscription = Subscription::where('company_id', $companyId)
            ->with('plan')
            ->latest()
            ->first();

        $company = \App\Models\Company::find($companyId);

        // ✅ حالة الاشتراك
        $status = 'no_subscription';
        $message = 'No active subscription';
        $daysLeft = null;
        $isExpiringSoon = false;
        $isPastDue = false;

        if ($subscription) {
            $status = $subscription->status;

            if ($subscription->ends_at) {
                $daysLeft = max(0, (int) now()->diffInDays($subscription->ends_at, false));
                $isExpiringSoon = $daysLeft <= 7 && $daysLeft > 0;
            }

            $isPastDue = $subscription->status === 'past_due';

            $message = match ($subscription->status) {
                'active' => $isExpiringSoon ? 'Your plan expires soon' : 'Active',
                'past_due' => 'Payment past due',
                'expired' => 'Subscription expired',
                'cancelled' => 'Cancelled',
                default => $subscription->status,
            };
        } elseif ($company && $company->status === 'trial') {
            $status = 'trial';
            $message = 'Trial period';
            if ($company->trial_ends_at) {
                $daysLeft = max(0, (int) now()->diffInDays($company->trial_ends_at, false));
                $isExpiringSoon = $daysLeft <= 3 && $daysLeft > 0;
            }
        }

        return response()->json([
            'msg' => 'Subscription status',
            'status' => 200,
            'data' => [
                'subscription_status' => $status,
                'message' => $message,
                'days_left' => $daysLeft,
                'is_expiring_soon' => $isExpiringSoon,
                'is_past_due' => $isPastDue,
                'plan_name' => $subscription?->plan?->name,
                'amount' => $subscription?->amount,
                'billing_cycle' => $subscription?->billing_cycle,
                'ends_at' => $subscription?->ends_at,
                'trial_ends_at' => $company?->trial_ends_at,
            ],
        ]);
    }
}
