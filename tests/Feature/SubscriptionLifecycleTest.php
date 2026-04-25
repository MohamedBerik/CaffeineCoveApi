<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $company;
    protected $basicPlan;
    protected $proPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Test Clinic',
            'slug' => 'test-clinic',
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'company_id' => $this->company->id,
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->basicPlan = Plan::create([
            'name' => 'Basic',
            'price_monthly' => 99,
            'is_active' => true,
        ]);

        $this->proPlan = Plan::create([
            'name' => 'Professional',
            'price_monthly' => 199,
            'is_active' => true,
        ]);

        Tenant::setId($this->company->id);
    }

    /** @test */
    public function subscription_lifecycle_flow()
    {
        // 1. إنشاء اشتراك جديد
        $subscription = Subscription::create([
            'company_id' => $this->company->id,
            'plan_id' => $this->basicPlan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99,
            'status' => 'active',
        ]);

        $this->assertEquals('active', $subscription->status);

        // 2. تغيير الخطة
        $subscription->update(['status' => 'changed']);

        $newSubscription = Subscription::create([
            'company_id' => $this->company->id,
            'plan_id' => $this->proPlan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 199,
            'status' => 'active',
        ]);

        $this->assertEquals('changed', $subscription->status);
        $this->assertEquals('active', $newSubscription->status);
        $this->assertEquals(199, $newSubscription->amount);

        // 3. انتهاء الاشتراك
        $newSubscription->update(['status' => 'expired']);
        $this->assertEquals('expired', $newSubscription->status);

        // 4. إلغاء الاشتراك
        $newSubscription->update(['status' => 'cancelled']);
        $this->assertEquals('cancelled', $newSubscription->status);
    }

    /** @test */
    public function only_one_active_subscription_per_company()
    {
        // إنشاء اشتراك نشط
        Subscription::create([
            'company_id' => $this->company->id,
            'plan_id' => $this->basicPlan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99,
            'status' => 'active',
        ]);

        // محاولة إنشاء اشتراك تاني
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/billing/subscribe', [
                'plan_id' => $this->proPlan->id,
                'billing_cycle' => 'monthly',
            ]);

        $response->assertStatus(422);
    }
}
