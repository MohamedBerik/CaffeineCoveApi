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

        $amount = $request->billing_cycle === 'monthly'
            ? $plan->price_monthly
            : ($plan->price_yearly ?? $plan->price_monthly * 10);

        $tax = $amount * 0.14; // 14% VAT
        $total = $amount + $tax;

        return DB::transaction(function () use ($companyId, $plan, $amount, $tax, $total) {
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

            // إنشاء فاتورة
            $invoice = BillingInvoice::create([
                'company_id' => $companyId,
                'subscription_id' => $subscription->id,
                'number' => BillingInvoice::generateNumber(),
                'amount' => $amount,
                'tax' => $tax,
                'total' => $total,
                'status' => 'paid', // مؤقت - لما نضيف بوابة دفع هيكون pending
                'paid_at' => now(),
                'due_date' => now()->addDays(7),
            ]);

            // تحديث حالة الشركة
            \App\Models\Company::where('id', $companyId)
                ->update(['status' => 'active']);

            return response()->json([
                'msg' => 'Subscription activated successfully',
                'status' => 200,
                'data' => $subscription,
                'invoice' => $invoice,
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
            return response()->json([
                'msg' => 'Current subscription',
                'status' => 200,
                'data' => $subscription,
            ]);
        }

        // ✅ لو مفيش اشتراك، رجع null (مش بيانات وهمية)
        return response()->json([
            'msg' => 'No active subscription',
            'status' => 200,
            'data' => null,
        ]);
    }
}
