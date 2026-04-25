<?php

namespace App\Http\Controllers\API\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\BillingInvoice;
use App\Models\PaymentMethod;
use App\Models\WebhookLog;
use App\Services\PayMobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayMobWebhookController extends Controller
{
    /**
     * Handle PayMob Webhook
     */
    public function handle(Request $request, PayMobService $paymob)
    {
        $payload = $request->all();
        $hmac = $payload['hmac'] ?? '';

        // ✅ تسجيل الـ Webhook للتدقيق
        $webhookLog = $this->logWebhook($request, $payload);

        // ✅ Idempotency Check - منع تكرار نفس الـ Webhook
        if ($this->isDuplicate($payload)) {
            Log::info('PayMob Webhook: Duplicate ignored', ['order_id' => $payload['obj']['order']['id'] ?? null]);
            return response()->json(['status' => 'duplicate_ignored']);
        }

        // ✅ Signature Validation
        if (!$paymob->verifyWebhook($payload)) {
            Log::warning('PayMob Webhook: Invalid HMAC', ['hmac' => $hmac]);

            $webhookLog->update([
                'status' => 'failed',
                'error' => 'Invalid HMAC signature',
            ]);

            return response()->json(['status' => 'invalid_signature'], 400);
        }

        try {
            return DB::transaction(function () use ($payload, $webhookLog) {
                $obj = $payload['obj'] ?? [];
                $success = $obj['success'] ?? false;
                $pending = $obj['pending'] ?? true;
                $orderId = $obj['order']['id'] ?? null;

                // ✅ التحقق من نجاح الدفع
                if (!$success || $pending) {
                    Log::info('PayMob Webhook: Payment not completed', [
                        'success' => $success,
                        'pending' => $pending,
                        'order_id' => $orderId,
                    ]);

                    $webhookLog->update(['status' => 'pending']);
                    return response()->json(['status' => 'payment_pending']);
                }

                // ✅ البحث عن الاشتراك
                $subscription = Subscription::where('payment_intent_id', $orderId)->first();

                if (!$subscription) {
                    Log::warning('PayMob Webhook: Subscription not found', ['order_id' => $orderId]);

                    $webhookLog->update([
                        'status' => 'failed',
                        'error' => 'Subscription not found',
                    ]);

                    return response()->json(['status' => 'subscription_not_found'], 404);
                }

                // ✅ تحديث حالة الاشتراك
                $subscription->update([
                    'status' => 'active',
                    'payment_token' => $obj['id'] ?? null,
                ]);

                // ✅ حفظ وسيلة الدفع
                if (isset($obj['source_data'])) {
                    $this->savePaymentMethod($subscription, $obj);
                }

                // ✅ إنشاء فاتورة
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

                // ✅ إطلاق Event
                event(new \App\Events\PaymentReceived($invoice));

                // ✅ تحديث سجل الـ Webhook
                $webhookLog->update([
                    'status' => 'success',
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                ]);

                Log::info('PayMob Webhook: Subscription activated', [
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                ]);

                return response()->json(['status' => 'ok']);
            });
        } catch (\Exception $e) {
            Log::error('PayMob Webhook: Error processing', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $webhookLog->update([
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     ✅ تسجيل الـ Webhook للتدقيق
     */
    private function logWebhook(Request $request, array $payload): WebhookLog
    {
        $orderId = $payload['obj']['order']['id'] ?? null;

        return WebhookLog::create([
            'gateway' => 'paymob',
            'event_type' => $payload['type'] ?? 'transaction.processed',
            'payload' => $payload,
            'order_id' => $orderId,
            'ip_address' => $request->ip(),
            'status' => 'received',
        ]);
    }

    /**
     ✅ Idempotency Check - منع تكرار نفس الـ Webhook
     */
    private function isDuplicate(array $payload): bool
    {
        $orderId = $payload['obj']['order']['id'] ?? null;
        $transactionId = $payload['obj']['id'] ?? null;

        if (!$orderId && !$transactionId) {
            return false;
        }

        // ✅ هل فيه Webhook بنفس الـ order_id واتنفذ بالفعل؟
        return WebhookLog::where('order_id', $orderId)
            ->where('status', 'success')
            ->exists();
    }

    /**
     ✅ حفظ وسيلة الدفع
     */
    private function savePaymentMethod(Subscription $subscription, array $obj): void
    {
        $sourceData = $obj['source_data'] ?? [];
        $cardLast4 = $sourceData['pan'] ?? substr($sourceData['masked_pan'] ?? '', -4);
        $cardBrand = $sourceData['sub_type'] ?? 'Card';

        if ($cardLast4) {
            PaymentMethod::updateOrCreate(
                [
                    'company_id' => $subscription->company_id,
                    'card_last4' => $cardLast4,
                ],
                [
                    'gateway' => 'paymob',
                    'token' => $obj['id'] ?? null,
                    'card_brand' => $cardBrand,
                    'card_exp_month' => 12,
                    'card_exp_year' => now()->addYears(3)->year,
                    'is_default' => true,
                ]
            );
        }
    }
}
