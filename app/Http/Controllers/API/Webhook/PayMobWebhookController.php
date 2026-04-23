<?php

namespace App\Http\Controllers\API\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\BillingInvoice;
use App\Models\PaymentMethod;
use App\Services\PayMobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayMobWebhookController extends Controller
{
    public function handle(Request $request, PayMobService $paymob)
    {
        $payload = $request->all();

        Log::info('PayMob Webhook Received', $payload);

        // التحقق من صحة الطلب
        if (!$paymob->verifyWebhook($payload)) {
            Log::warning('PayMob Webhook: Invalid HMAC');
            return response()->json(['status' => 'invalid'], 400);
        }

        // التحقق من نجاح الدفع
        $obj = $payload['obj'] ?? [];
        $success = $obj['success'] ?? false;
        $pending = $obj['pending'] ?? true;
        $orderId = $obj['order']['id'] ?? null;

        if (!$success || $pending) {
            Log::info('PayMob Webhook: Payment not completed', ['success' => $success, 'pending' => $pending]);
            return response()->json(['status' => 'ignored']);
        }

        // البحث عن الاشتراك
        $subscription = Subscription::where('payment_intent_id', $orderId)->first();

        if (!$subscription) {
            Log::warning('PayMob Webhook: Subscription not found', ['order_id' => $orderId]);
            return response()->json(['status' => 'not_found'], 404);
        }

        // تحديث حالة الاشتراك
        $subscription->update([
            'status' => 'active',
            'payment_token' => $obj['id'] ?? null,
        ]);

        // حفظ وسيلة الدفع (لو متاحة)
        if (isset($obj['payment_key_claims']['card'])) {
            $card = $obj['payment_key_claims']['card'];
            PaymentMethod::updateOrCreate(
                [
                    'company_id' => $subscription->company_id,
                    'token' => $card['token'] ?? $obj['id'],
                ],
                [
                    'gateway' => 'paymob',
                    'card_brand' => $card['subtype'] ?? 'Card',
                    'card_last4' => $card['last4'] ?? substr($card['masked_pan'] ?? '', -4),
                    'card_exp_month' => $card['expiry_month'] ?? 12,
                    'card_exp_year' => $card['expiry_year'] ?? 2026,
                    'is_default' => true,
                ]
            );
        }

        // إنشاء فاتورة
        $invoice = BillingInvoice::create([
            'company_id' => $subscription->company_id,
            'subscription_id' => $subscription->id,
            'number' => BillingInvoice::generateNumber(),
            'amount' => $subscription->amount,
            'tax' => $subscription->amount * 0.14,
            'total' => $subscription->amount * 1.14,
            'status' => 'paid',
            'paid_at' => now(),
            'transaction_id' => $obj['id'] ?? null,
        ]);

        Log::info('PayMob Webhook: Subscription activated', ['subscription_id' => $subscription->id]);
        event(new \App\Events\PaymentReceived($invoice));

        return response()->json(['status' => 'ok']);
    }
}
