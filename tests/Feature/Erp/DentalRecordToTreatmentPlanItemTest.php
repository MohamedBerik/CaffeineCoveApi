<?php

namespace Tests\Feature\Erp;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Procedure;
use App\Models\TreatmentPlan;
use App\Models\DentalRecord;

class DentalRecordToTreatmentPlanItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_convert_dental_record_to_treatment_plan_item()
    {
        $user = $this->actingAsUserWithCompany();
        $companyId = $user->company_id;

        $customer = Customer::factory()->create([
            'company_id' => $companyId,
            'status' => '1',
        ]);

        $procedure = Procedure::create([
            'company_id' => $companyId,
            'name' => 'Filling',
            'default_price' => 250,
            'is_active' => true,
        ]);

        $plan = TreatmentPlan::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'title' => 'Dental Plan A',
            'notes' => null,
            'total_cost' => 1000,
            'status' => 'active',
        ]);

        $record = DentalRecord::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'appointment_id' => null,
            'procedure_id' => $procedure->id,
            'tooth_number' => '16',
            'surface' => 'occlusal',
            'status' => 'planned',
            'notes' => 'Needs filling',
        ]);

        $res = $this->postJson("/api/erp/dental-records/{$record->id}/to-treatment-plan-item", [
            'treatment_plan_id' => $plan->id,
        ]);

        $res->assertStatus(201);

        $this->assertDatabaseHas('treatment_plan_items', [
            'company_id' => $companyId,
            'treatment_plan_id' => $plan->id,
            'procedure_id' => $procedure->id,
            'procedure' => 'Filling',
            'tooth_number' => '16',
            'surface' => 'occlusal',
            'price' => 250,
        ]);
    }

    public function test_cannot_convert_same_dental_record_twice_to_same_plan_item()
    {
        $user = $this->actingAsUserWithCompany();
        $companyId = $user->company_id;

        $customer = Customer::factory()->create([
            'company_id' => $companyId,
            'status' => '1',
        ]);

        $procedure = Procedure::create([
            'company_id' => $companyId,
            'name' => 'Root Canal',
            'default_price' => 500,
            'is_active' => true,
        ]);

        $plan = TreatmentPlan::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'title' => 'Dental Plan B',
            'notes' => null,
            'total_cost' => 2000,
            'status' => 'active',
        ]);

        $record = DentalRecord::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'appointment_id' => null,
            'procedure_id' => $procedure->id,
            'tooth_number' => '11',
            'surface' => null,
            'status' => 'planned',
            'notes' => null,
        ]);

        $first = $this->postJson("/api/erp/dental-records/{$record->id}/to-treatment-plan-item", [
            'treatment_plan_id' => $plan->id,
        ]);
        $first->assertStatus(201);

        $second = $this->postJson("/api/erp/dental-records/{$record->id}/to-treatment-plan-item", [
            'treatment_plan_id' => $plan->id,
        ]);
        $second->assertStatus(409);
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
