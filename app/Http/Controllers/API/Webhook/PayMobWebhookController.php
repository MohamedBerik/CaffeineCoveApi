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
use Illuminate\Support\Facades\Cache;

class PayMobWebhookController extends Controller
{
    /**
     * Handle PayMob Webhook - Production Ready
     *
     * Features:
     * ✅ Atomic Idempotency (Redis Lock)
     * ✅ Row-Level Database Lock
     * ✅ Grace Period Calculation
     * ✅ Duplicate Prevention
     * ✅ Concurrent Request Protection
     */
    public function handle(Request $request, PayMobService $paymob)
    {
        $payload = $request->all();

        // استخراج المعرفات الأساسية
        $transactionId = $payload['obj']['id'] ?? null;
        $orderId = $payload['obj']['order']['id'] ?? null;
        $success = $payload['obj']['success'] ?? false;
        $pending = $payload['obj']['pending'] ?? true;

        // ✅ تسجيل الـ Webhook فوراً (قبل أي معالجة)
        $webhookLog = $this->logWebhook($request, $payload);

        // ⚡ منع المعالجة المتزامنة - Atomic Redis Lock
        $lockKey = "paymob_webhook:{$orderId}:{$transactionId}";
        $lock = Cache::lock($lockKey, 30); // 30 ثانية timeout

        if (!$lock->get()) {
            Log::warning('PayMob Webhook: Concurrent request blocked', [
                'order_id' => $orderId,
                'transaction_id' => $transactionId,
            ]);

            $webhookLog->update([
                'status' => 'duplicate_ignored',
                'error' => 'Concurrent request prevented by lock',
            ]);

            return response()->json([
                'status' => 'processing_in_progress',
                'message' => 'Request is being processed'
            ], 409);
        }

        try {
            // ✅ التحقق من صحة التوقيع
            if (!$this->verifyPaymobSignature($payload, $paymob)) {
                Log::warning('PayMob Webhook: Invalid signature', [
                    'order_id' => $orderId,
                    'transaction_id' => $transactionId,
                ]);

                $webhookLog->update([
                    'status' => 'failed',
                    'error' => 'Invalid HMAC signature',
                ]);

                return response()->json(['status' => 'invalid_signature'], 400);
            }

            // ✅ التحقق من نجاح الدفع
            if (!$success || $pending) {
                Log::info('PayMob Webhook: Payment incomplete', [
                    'success' => $success,
                    'pending' => $pending,
                    'order_id' => $orderId,
                ]);

                $webhookLog->update(['status' => 'pending']);
                return response()->json(['status' => 'payment_pending']);
            }

            // ⚡ المعالجة الرئيسية داخل Transaction
            return DB::transaction(function () use ($payload, $orderId, $transactionId, $webhookLog) {

                // ✅ البحث عن الاشتراك مع قفل الصف (Row-Level Lock)
                $subscription = Subscription::where('payment_intent_id', $orderId)
                    ->lockForUpdate() // ⚡ يمنع أي تعديل متزامن
                    ->first();

                if (!$subscription) {
                    Log::warning('PayMob Webhook: Subscription not found', [
                        'order_id' => $orderId,
                    ]);

                    $webhookLog->update([
                        'status' => 'failed',
                        'error' => 'Subscription not found',
                    ]);

                    return response()->json([
                        'status' => 'subscription_not_found',
                        'message' => 'No subscription matches this payment'
                    ], 404);
                }

                // ✅ Idempotency Check: هل الاشتراك نشط بالفعل؟
                if ($subscription->status === 'active' && $subscription->payment_token) {
                    Log::info('PayMob Webhook: Already processed (idempotent)', [
                        'subscription_id' => $subscription->id,
                        'order_id' => $orderId,
                        'payment_token' => $subscription->payment_token,
                    ]);

                    $webhookLog->update([
                        'status' => 'duplicate_ignored',
                        'subscription_id' => $subscription->id,
                        'error' => 'Subscription already active',
                    ]);

                    return response()->json([
                        'status' => 'already_active',
                        'message' => 'Subscription was already activated'
                    ]);
                }

                // ✅ هل تم معالجة نفس الـ Webhook بالضبط من قبل؟
                $duplicateWebhook = WebhookLog::where('order_id', $orderId)
                    ->where('status', 'success')
                    ->where('id', '!=', $webhookLog->id)
                    ->exists();

                if ($duplicateWebhook) {
                    Log::warning('PayMob Webhook: Duplicate detected via WebhookLog', [
                        'order_id' => $orderId,
                    ]);

                    $webhookLog->update([
                        'status' => 'duplicate_ignored',
                        'subscription_id' => $subscription->id,
                        'error' => 'Duplicate webhook transaction',
                    ]);

                    return response()->json([
                        'status' => 'duplicate_webhook',
                        'message' => 'This webhook was already processed'
                    ]);
                }

                // ✅ حساب فترة السماح (Grace Period)
                $gracePeriodDays = config('billing.grace_period_days', 7);
                $subscriptionEndsAt = $subscription->ends_at ?? now()->addMonth();
                $graceEndsAt = now()->addDays($gracePeriodDays);

                // ✅ تحديث الاشتراك (Atomic Update)
                $subscription->update([
                    'status' => 'active',
                    'starts_at' => $subscription->starts_at ?? now(),
                    'ends_at' => $subscriptionEndsAt,
                    'grace_period_ends_at' => $graceEndsAt,
                    'payment_token' => $transactionId,
                    'transaction_id' => $transactionId,
                ]);

                // ✅ حفظ طريقة الدفع
                if (isset($payload['obj']['source_data'])) {
                    $this->savePaymentMethod($subscription, $payload['obj']);
                }

                // ✅ إنشاء الفاتورة (مع فحص مسبق)
                $invoice = $this->createInvoiceSafely($subscription, $transactionId);

                // ✅ إطلاق الحدث
                event(new \App\Events\PaymentReceived($invoice));

                // ✅ تحديث سجل الـ Webhook
                $webhookLog->update([
                    'status' => 'success',
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                ]);

                Log::info('PayMob Webhook: Payment processed successfully', [
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                    'transaction_id' => $transactionId,
                    'grace_ends_at' => $graceEndsAt->toDateTimeString(),
                ]);

                return response()->json([
                    'status' => 'ok',
                    'message' => 'Payment processed successfully',
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                ]);
            }); // End DB::transaction

        } catch (\Exception $e) {
            Log::error('PayMob Webhook: Processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_id' => $orderId,
                'transaction_id' => $transactionId,
            ]);

            $webhookLog->update([
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Temporary processing error'
            ], 500);
        } finally {
            // ✅ تحرير القفل دائماً (حتى في حالة الخطأ)
            optional($lock)->release();
        }
    }

    /**
     * التحقق من صحة توقيع PayMob
     */
    private function verifyPaymobSignature(array $payload, PayMobService $paymob): bool
    {
        try {
            return $paymob->verifyWebhook($payload);
        } catch (\Exception $e) {
            Log::error('PayMob signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * تسجيل الـ Webhook للتدقيق
     */
    private function logWebhook(Request $request, array $payload): WebhookLog
    {
        $orderId = $payload['obj']['order']['id'] ?? null;
        $transactionId = $payload['obj']['id'] ?? null;

        return WebhookLog::create([
            'gateway' => 'paymob',
            'event_type' => $payload['type'] ?? 'transaction.processed',
            'payload' => $payload,
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'ip_address' => $request->ip(),
            'status' => 'received',
        ]);
    }

    /**
     * إنشاء الفاتورة بشكل آمن (مع فحص التكرار)
     */
    private function createInvoiceSafely(Subscription $subscription, string $transactionId): BillingInvoice
    {
        // ✅ فحص وجود فاتورة بنفس رقم العملية
        $existingInvoice = BillingInvoice::where('transaction_id', $transactionId)
            ->where('subscription_id', $subscription->id)
            ->first();

        if ($existingInvoice) {
            Log::info('PayMob Webhook: Using existing invoice', [
                'invoice_id' => $existingInvoice->id,
            ]);
            return $existingInvoice;
        }

        // ✅ إنشاء فاتورة جديدة
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
            Log::error('PayMob Webhook: Failed to save payment method', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);
            // لا نوقف العملية إذا فشل حفظ طريقة الدفع
        }
    }
}
