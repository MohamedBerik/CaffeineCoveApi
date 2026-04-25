<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $company;
    protected $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // إنشاء شركة ومستخدم Admin
        $this->company = Company::create([
            'name' => 'Test Clinic',
            'slug' => 'test-clinic',
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'company_id' => $this->company->id,
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // إنشاء خطة
        $this->plan = Plan::create([
            'name' => 'Basic',
            'price_monthly' => 99,
            'price_yearly' => 990,
            'is_active' => true,
        ]);

        // تعيين Tenant Context
        Tenant::setId($this->company->id);
    }

    /** @test */
    public function user_can_view_current_subscription()
    {
        // إنشاء اشتراك
        Subscription::create([
            'company_id' => $this->company->id,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/billing/subscription');

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.amount', '99.00');
    }

    /** @test */
    public function user_can_view_available_plans()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/billing/plans');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    /** @test */
    public function user_can_subscribe_to_plan()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/billing/subscribe', [
                'plan_id' => $this->plan->id,
                'billing_cycle' => 'monthly',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $this->company->id,
            'plan_id' => $this->plan->id,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function user_cannot_subscribe_with_invalid_plan()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/billing/subscribe', [
                'plan_id' => 999,
                'billing_cycle' => 'monthly',
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function user_can_cancel_subscription()
    {
        // إنشاء اشتراك نشط
        Subscription::create([
            'company_id' => $this->company->id,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/billing/cancel');

        $response->assertStatus(200);

        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $this->company->id,
            'status' => 'cancelled',
        ]);
    }
}
