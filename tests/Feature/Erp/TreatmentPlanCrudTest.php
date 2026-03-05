<?php

namespace Tests\Feature\Erp;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use App\Models\TreatmentPlan;
use App\Models\Invoice;
use App\Models\Order;

class TreatmentPlanCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_treatment_plans_scoped_to_company()
    {
        $user = $this->actingAsAdminWithCompany();
        $companyId = $user->company_id;

        $customer = Customer::factory()->create(['company_id' => $companyId]);

        TreatmentPlan::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'title' => 'Plan A',
            'notes' => null,
            'total_cost' => 1000,
            'status' => 'active',
        ]);

        // Plan لشركة أخرى لازم ما يظهرش
        $otherCompany = Company::factory()->create();
        $otherCustomer = Customer::factory()->create(['company_id' => $otherCompany->id]);

        TreatmentPlan::create([
            'company_id' => $otherCompany->id,
            'customer_id' => $otherCustomer->id,
            'title' => 'Other Plan',
            'notes' => null,
            'total_cost' => 500,
            'status' => 'active',
        ]);

        $res = $this->getJson('/api/erp/treatment-plans');
        $res->assertOk();

        // لأن index بيرجع paginator raw => data جوّا "data"
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals('Plan A', $res->json('data.0.title'));
    }

    public function test_can_create_treatment_plan()
    {
        $user = $this->actingAsAdminWithCompany();
        $companyId = $user->company_id;

        $customer = Customer::factory()->create(['company_id' => $companyId]);

        $res = $this->postJson('/api/erp/treatment-plans', [
            'customer_id' => $customer->id,
            'title' => 'Root Canal Plan',
            'notes' => 'Initial plan',
            'total_cost' => 1500,
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('treatment_plans', [
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'title' => 'Root Canal Plan',
            'status' => 'active',
        ]);
    }

    public function test_cannot_create_plan_for_customer_from_other_company()
    {
        $user = $this->actingAsAdminWithCompany();
        $companyId = $user->company_id;

        $otherCompany = Company::factory()->create();
        $otherCustomer = Customer::factory()->create(['company_id' => $otherCompany->id]);

        $res = $this->postJson('/api/erp/treatment-plans', [
            'customer_id' => $otherCustomer->id,
            'title' => 'Should Fail',
            'total_cost' => 100,
        ]);

        $res->assertStatus(422); // بعد ما تعدّل validation بـ Rule::exists scoped
    }

    public function test_can_show_plan_only_within_company()
    {
        $user = $this->actingAsAdminWithCompany();
        $companyId = $user->company_id;

        $customer = Customer::factory()->create(['company_id' => $companyId]);

        $plan = TreatmentPlan::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'title' => 'Plan X',
            'notes' => null,
            'total_cost' => 700,
            'status' => 'active',
        ]);

        $res = $this->getJson("/api/erp/treatment-plans/{$plan->id}");
        $res->assertOk();
        $this->assertEquals('Plan X', $res->json('title'));
    }

    public function test_can_update_plan()
    {
        $user = $this->actingAsAdminWithCompany();
        $companyId = $user->company_id;

        $customer = Customer::factory()->create(['company_id' => $companyId]);

        $plan = TreatmentPlan::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'title' => 'Plan Old',
            'notes' => null,
            'total_cost' => 700,
            'status' => 'active',
        ]);

        $res = $this->putJson("/api/erp/treatment-plans/{$plan->id}", [
            'title' => 'Plan New',
            'status' => 'completed',
        ]);

        $res->assertOk();
        $this->assertDatabaseHas('treatment_plans', [
            'id' => $plan->id,
            'company_id' => $companyId,
            'title' => 'Plan New',
            'status' => 'completed',
        ]);
    }

    public function test_can_delete_plan_when_no_invoices()
    {
        $user = $this->actingAsAdminWithCompany();
        $companyId = $user->company_id;

        $customer = Customer::factory()->create(['company_id' => $companyId]);

        $plan = TreatmentPlan::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'title' => 'Plan Delete',
            'notes' => null,
            'total_cost' => 700,
            'status' => 'active',
        ]);

        $res = $this->deleteJson("/api/erp/treatment-plans/{$plan->id}");
        $res->assertOk();

        $this->assertDatabaseMissing('treatment_plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_cannot_delete_plan_with_linked_invoices()
    {
        $user = $this->actingAsAdminWithCompany();
        $companyId = $user->company_id;

        $customer = Customer::factory()->create(['company_id' => $companyId]);

        $plan = TreatmentPlan::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'title' => 'Plan Protected',
            'notes' => null,
            'total_cost' => 700,
            'status' => 'active',
        ]);

        $order = Order::create([
            'company_id'      => $companyId,
            'customer_id'     => $customer->id,
            'title_en'        => 'Treatment Plan',
            'title_ar'        => 'خطة علاج',
            'description_en'  => 'Test order for treatment plan invoice',
            'description_ar'  => 'أوردر تجريبي لفاتورة خطة العلاج',
            'status'          => 'confirmed',
            'total'           => 700,
            'created_by'      => $user->id,
        ]);

        $invoice = Invoice::create([
            'company_id'        => $companyId,
            'number'            => 'INV-TEST-1',
            'order_id'          => $order->id,          // ✅ required
            'appointment_id'    => null,                // لو NOT NULL عندك غيّرها لرقم (شوف تحت)
            'treatment_plan_id' => $plan->id,
            'customer_id'       => $customer->id,
            'total'             => 700,
            'status'            => 'unpaid',
            'issued_at'         => now(),
        ]);

        $res = $this->deleteJson("/api/erp/treatment-plans/{$plan->id}");
        $res->assertStatus(422);
    }

    private function actingAsAdminWithCompany(): User
    {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'admin',
            'is_super_admin' => false,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }
}
