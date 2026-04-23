<?php

namespace App\Services;

use App\Exceptions\BillingException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayMobService
{
    protected $apiKey;
    protected $integrationId;
    protected $iframeId;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.paymob.api_key');
        $this->integrationId = config('services.paymob.integration_id');
        $this->iframeId = config('services.paymob.iframe_id');
        $this->baseUrl = 'https://accept.paymob.com/api';
    }

    /**
     * إنشاء نية دفع جديدة
     */
    public function createIntention(array $data): array
    {
        try {
            // Step 1: Authentication
            $auth = $this->authenticate();

            // Step 2: Order Registration
            $order = $this->createOrder($auth['token'], $data);

            // Step 3: Payment Key
            $paymentKey = $this->createPaymentKey($auth['token'], $order['id'], $data);

            return [
                'id' => $order['id'],
                'iframe_url' => "https://accept.paymob.com/api/acceptance/iframes/{$this->iframeId}?payment_token={$paymentKey['token']}",
            ];
        } catch (BillingException $e) {
            // ✅ BillingException بتمرر عادي
            throw $e;
        } catch (\Exception $e) {
            // ✅ أي Exception تانية بنحولها لـ BillingException
            Log::error('PayMob Error: ' . $e->getMessage());
            throw new BillingException(
                'Payment processing failed. Please try again.',
                500,
                'PAYMENT_GATEWAY_ERROR',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * الحصول على Token المصادقة
     */
    private function authenticate(): array
    {
        $response = Http::post($this->baseUrl . '/auth/tokens', [
            'api_key' => $this->apiKey,
        ]);

        if (!$response->successful()) {
            throw new BillingException(
                'Payment gateway authentication failed',
                500,
                'PAYMENT_GATEWAY_ERROR',
                ['response' => $response->body()]
            );
        }

        return $response->json();
    }

    /**
     * إنشاء طلب
     */
    private function createOrder(string $authToken, array $data): array
    {
        $response = Http::post($this->baseUrl . '/ecommerce/orders', [
            'auth_token' => $authToken,
            'delivery_needed' => false,
            'amount_cents' => $data['amount'],
            'currency' => $data['currency'] ?? 'EGP',
            'items' => [],
            'merchant_order_id' => $data['metadata']['subscription_id'] ?? null,
        ]);

        if (!$response->successful()) {
            throw new BillingException(
                'Order creation failed',
                500,
                'PAYMENT_ORDER_ERROR',
                ['response' => $response->body()]
            );
        }

        return $response->json();
    }

    /**
     * إنشاء مفتاح الدفع
     */
    private function createPaymentKey(string $authToken, string $orderId, array $data): array
    {
        $user = auth()->user();

        $billingData = [
            'email' => $user->email ?? 'customer@example.com',
            'first_name' => $user->name ?? 'Customer',
            'last_name' => $user->name ?? 'Customer',
            'phone_number' => $user->phone ?? '01234567890',
            'apartment' => 'NA',
            'floor' => 'NA',
            'street' => 'NA',
            'building' => 'NA',
            'shipping_method' => 'NA',
            'postal_code' => 'NA',
            'city' => 'Cairo',
            'country' => 'EG',
            'state' => 'Cairo',
        ];

        $response = Http::post($this->baseUrl . '/acceptance/payment_keys', [
            'auth_token' => $authToken,
            'amount_cents' => $data['amount'],
            'expiration' => 3600,
            'order_id' => $orderId,
            'billing_data' => $billingData,
            'currency' => $data['currency'] ?? 'EGP',
            'integration_id' => $this->integrationId,
            'lock_order_when_paid' => true,
            'single_payment_attempt' => false,
            'disable_3ds' => true,
            'is_live' => false,
            'redirection_url' => config('app.url') . '/billing/callback',
        ]);

        if (!$response->successful()) {
            throw new BillingException(
                'Payment key creation failed',
                500,
                'PAYMENT_KEY_ERROR',
                ['response' => $response->body()]
            );
        }

        return $response->json();
    }

    /**
     * التحقق من صحة Webhook
     */
    public function verifyWebhook(array $payload): bool
    {
        $hmac = $payload['hmac'] ?? '';
        unset($payload['hmac']);

        $computedHmac = hash_hmac('sha512', json_encode($payload), $this->apiKey);

        return hash_equals($hmac, $computedHmac);
    }
}
