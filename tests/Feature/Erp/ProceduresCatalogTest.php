<?php

namespace Tests\Feature\Erp;

use App\Models\Company;
use App\Models\Procedure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProceduresCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_crud_procedures_scoped_to_company()
    {
        $user = $this->actingAsAdminWithCompany();
        $companyId = $user->company_id;

        // create
        $create = $this->postJson('/api/erp/procedures', [
            'name' => 'Dental Cleaning',
            'default_price' => 250,
            'is_active' => true,
        ]);

        $create->assertStatus(201);
        $procedureId = $create->json('data.id');

        $this->assertDatabaseHas('procedures', [
            'id' => $procedureId,
            'company_id' => $companyId,
            'name' => 'Dental Cleaning',
        ]);

        // list scoped
        $list = $this->getJson('/api/erp/procedures');
        $list->assertStatus(200);

        // update
        $update = $this->putJson("/api/erp/procedures/{$procedureId}", [
            'default_price' => 300,
        ]);
        $update->assertStatus(200);

        $this->assertDatabaseHas('procedures', [
            'id' => $procedureId,
            'company_id' => $companyId,
            'default_price' => 300,
        ]);

        // soft delete (deactivate)
        $del = $this->deleteJson("/api/erp/procedures/{$procedureId}");
        $del->assertStatus(200);

        $this->assertDatabaseHas('procedures', [
            'id' => $procedureId,
            'company_id' => $companyId,
            'is_active' => 0,
        ]);
    }

    public function test_cannot_touch_other_company_procedure()
    {
        $userA = $this->actingAsAdminWithCompany();
        $companyA = $userA->company_id;

        $companyB = Company::factory()->create();
        $procB = Procedure::factory()->create([
            'company_id' => $companyB->id,
            'name' => 'Root Canal',
        ]);

        // update should 404 (scoped)
        $res = $this->putJson("/api/erp/procedures/{$procB->id}", [
            'default_price' => 999,
        ]);

        $res->assertStatus(404);

        $this->assertDatabaseHas('procedures', [
            'id' => $procB->id,
            'company_id' => $companyB->id,
            'name' => 'Root Canal',
        ]);
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
