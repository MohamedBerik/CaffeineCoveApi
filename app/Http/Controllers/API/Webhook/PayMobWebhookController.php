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
use Illuminate\Support\Facades\Mail;

class PayMobWebhookController extends Controller
{
    /**
     * Handle PayMob Webhook - Database-Safe Idempotency
     */
    public function handle(Request $request, PayMobService $paymob)
    {
        $payload = $request->all();

        $transactionId = $payload['obj']['id'] ?? null;
        $orderId = $payload['obj']['order']['id'] ?? null;
        $success = $payload['obj']['success'] ?? false;
        $pending = $payload['obj']['pending'] ?? true;

        // ✅ تسجيل الـ Webhook فوراً
        $webhookLog = $this->logWebhook($request, $payload);

        // ✅ التحقق من صحة التوقيع
        if (!$paymob->verifyWebhook($payload)) {
            Log::warning('PayMob Webhook: Invalid HMAC', ['order_id' => $orderId]);
            $webhookLog->update(['status' => 'failed', 'error' => 'Invalid HMAC signature']);
            return response()->json(['status' => 'invalid_signature'], 400);
        }

        // ✅ التحقق من نجاح الدفع
        if (!$success || $pending) {
            Log::info('PayMob Webhook: Payment incomplete', ['order_id' => $orderId]);
            $webhookLog->update(['status' => 'pending']);
            return response()->json(['status' => 'payment_pending']);
        }

        try {
            return DB::transaction(function () use ($payload, $orderId, $transactionId, $webhookLog) {

                // ✅ البحث عن الاشتراك مع Row-Level Lock
                $subscription = Subscription::where('payment_intent_id', $orderId)
                    ->lockForUpdate()
                    ->first();

                if (!$subscription) {
                    Log::warning('PayMob Webhook: Subscription not found', ['order_id' => $orderId]);
                    $webhookLog->update(['status' => 'failed', 'error' => 'Subscription not found']);
                    return response()->json(['status' => 'subscription_not_found'], 404);
                }

                // ✅ Idempotency Check: هل الاشتراك نشط بالفعل؟
                if ($subscription->status === 'active' && $subscription->payment_token) {
                    Log::info('PayMob Webhook: Already processed (idempotent)', [
                        'subscription_id' => $subscription->id,
                        'order_id' => $orderId,
                    ]);
                    $webhookLog->update(['status' => 'duplicate_ignored', 'subscription_id' => $subscription->id]);
                    return response()->json(['status' => 'already_active']);
                }

                // ✅ هل تم معالجة نفس الـ Webhook من قبل؟
                $duplicateWebhook = WebhookLog::where('order_id', $orderId)
                    ->where('status', 'success')
                    ->where('id', '!=', $webhookLog->id)
                    ->exists();

                if ($duplicateWebhook) {
                    Log::warning('PayMob Webhook: Duplicate detected', ['order_id' => $orderId]);
                    $webhookLog->update(['status' => 'duplicate_ignored', 'subscription_id' => $subscription->id]);
                    return response()->json(['status' => 'duplicate_webhook']);
                }

                // ✅ حساب Grace Period
                $gracePeriodDays = config('billing.grace_period_days', 7);
                $graceEndsAt = now()->addDays($gracePeriodDays);

                // ✅ تحديث الاشتراك
                $subscription->update([
                    'status' => 'active',
                    'starts_at' => $subscription->starts_at ?? now(),
                    'ends_at' => $subscription->ends_at ?? now()->addMonth(),
                    'grace_period_ends_at' => $graceEndsAt,
                    'payment_token' => $transactionId,
                    'transaction_id' => $transactionId,
                ]);

                // ✅ حفظ طريقة الدفع
                if (isset($payload['obj']['source_data'])) {
                    $this->savePaymentMethod($subscription, $payload['obj']);
                }

                // ✅ إنشاء الفاتورة
                $invoice = $this->createInvoiceSafely($subscription, $transactionId);

                // ✅ إطلاق Event
                event(new \App\Events\PaymentReceived($invoice));

                // ✅ تحديث سجل الـ Webhook
                $webhookLog->update([
                    'status' => 'success',
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                ]);

                Log::info('PayMob Webhook: Payment processed', [
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                    'transaction_id' => $transactionId,
                ]);

                return response()->json([
                    'status' => 'ok',
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                ]);
            });
        } catch (\Exception $e) {
            Log::critical('PayMob Webhook: Processing failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'transaction_id' => $transactionId,
            ]);

            $webhookLog->update(['status' => 'error', 'error' => $e->getMessage()]);

            // ✅ إرسال إشعار للإدارة في حالة الفشل
            try {
                if (app()->environment('production')) {
                    Mail::raw(
                        "🚨 PayMob Webhook Failed\n\nError: {$e->getMessage()}\nOrder: {$orderId}\nTime: " . now(),
                        function ($message) {
                            $message->to(config('mail.from.address'))
                                ->subject('🚨 Critical: PayMob Webhook Failed');
                        }
                    );
                }
            } catch (\Exception $mailException) {
                Log::error('Failed to send webhook alert email', ['error' => $mailException->getMessage()]);
            }

            return response()->json(['status' => 'error', 'message' => 'Temporary processing error'], 500);
        }
    }

    /**
     * تسجيل الـ Webhook
     */
    private function logWebhook(Request $request, array $payload): WebhookLog
    {
        return WebhookLog::create([
            'gateway' => 'paymob',
            'event_type' => $payload['type'] ?? 'transaction.processed',
            'payload' => $payload,
            'order_id' => $payload['obj']['order']['id'] ?? null,
            'transaction_id' => $payload['obj']['id'] ?? null,
            'ip_address' => $request->ip(),
            'status' => 'received',
        ]);
    }

    /**
     * إنشاء الفاتورة بشكل آمن
     */
    private function createInvoiceSafely(Subscription $subscription, string $transactionId): BillingInvoice
    {
        $existingInvoice = BillingInvoice::where('transaction_id', $transactionId)
            ->where('subscription_id', $subscription->id)
            ->first();

        if ($existingInvoice) {
            return $existingInvoice;
        }

        return BillingInvoice::create([
            'company_id' => $subscription->company_id,
            'subscription_id' => $subscription->id,
            'number' => BillingInvoice::generateNumber(),
            'amount' => $subscription->amount,
            'tax' => $subscription->amount * 0.14,
            'total' => $subscription->amount * 1.14,
            'status' => 'paid',
            'paid_at' => now(),
            'due_date' => now()->addMonth(),
            'payment_method' => 'card',
            'transaction_id' => $transactionId,
        ]);
    }

    /**
     * حفظ طريقة الدفع
     */
    private function savePaymentMethod(Subscription $subscription, array $obj): void
    {
        try {
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
                        'card_exp_month' => $sourceData['exp_month'] ?? 12,
                        'card_exp_year' => $sourceData['exp_year'] ?? now()->addYears(3)->year,
                        'is_default' => true,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to save payment method', ['error' => $e->getMessage()]);
        }
    }
}
