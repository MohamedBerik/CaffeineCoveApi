<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class SecurityTest extends TestCase
{
    public function test_unauthenticated_users_cannot_access_api()
    {
        $response = $this->getJson('/api/erp/dashboard');
        $response->assertStatus(401);
    }

    public function test_webhook_endpoint_is_accessible()
    {
        $response = $this->postJson('/api/webhooks/paymob', [
            'test' => 'data'
        ]);

        // ✅ المفروض يرد بـ 400 (invalid signature) مش 401 (unauthorized)
        $this->assertNotEquals(401, $response->status());
    }

    public function test_login_rate_limiting()
    {
        // إرسال 6 طلبات في ثانية
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'test@test.com',
                'password' => 'wrong',
            ]);
        }

        // الطلب السادس يجب أن يكون 429 Too Many Requests
        $response->assertStatus(429);
    }

    public function test_sensitive_data_not_exposed()
    {
        $response = $this->getJson('/api/me');

        // تأكد من عدم وجود بيانات حساسة
        $this->assertArrayNotHasKey('password', $response->json());
        $this->assertArrayNotHasKey('api_key', $response->json());
    }
}
