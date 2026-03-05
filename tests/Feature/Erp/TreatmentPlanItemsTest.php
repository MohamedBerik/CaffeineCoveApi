<?php

namespace Tests\Feature\Erp;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use App\Models\TreatmentPlan;

class TreatmentPlanItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_list_update_delete_items_scoped_to_company()
    {
        $user = $this->actingAsUserWithCompany();
        $companyId = $user->company_id;

        $customer = Customer::factory()->create([
            'company_id' => $companyId,
            'name' => 'Patient A',
        ]);

        $plan = TreatmentPlan::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'title' => 'Plan A',
            'notes' => null,
            'total_cost' => 1000,
            'status' => 'active',
        ]);

        // Create procedure (catalog)
        $procedure = \App\Models\Procedure::create([
            'company_id' => $companyId,
            'name' => 'Filling',
            'price' => 250,
        ]);

        // Add item
        $res = $this->postJson("/api/erp/treatment-plans/{$plan->id}/items", [
            'procedure_id' => $procedure->id,
            'tooth_number' => '16',
            'surface' => 'occlusal',
            'price' => 250,
        ]);
        $res->assertStatus(201);
        $itemId = $res->json('data.id');

        // List items
        $list = $this->getJson("/api/erp/treatment-plans/{$plan->id}/items");
        $list->assertStatus(200);
        $this->assertCount(1, $list->json('data'));

        // Update item
        $upd = $this->putJson("/api/erp/treatment-plan-items/{$itemId}", [
            'price' => 300,
            'notes' => 'Updated',
        ]);
        $upd->assertStatus(200);

        $this->assertDatabaseHas('treatment_plan_items', [
            'id' => $itemId,
            'company_id' => $companyId,
            'treatment_plan_id' => $plan->id,
            'price' => 300,
        ]);

        // Delete item
        $del = $this->deleteJson("/api/erp/treatment-plan-items/{$itemId}");
        $del->assertStatus(200);

        $this->assertDatabaseMissing('treatment_plan_items', [
            'id' => $itemId,
        ]);
    }

    private function actingAsUserWithCompany(): User
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'admin',
            'is_super_admin' => 0,
        ]);

        Sanctum::actingAs($user);
        return $user;
    }
}
