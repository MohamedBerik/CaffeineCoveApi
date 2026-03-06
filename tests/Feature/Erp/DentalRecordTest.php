<?php

namespace Tests\Feature\Erp;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Doctor;
use App\Models\Appointment;
use App\Models\Procedure;
use App\Models\DentalRecord;

class DentalRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_crud_dental_record_scoped_to_company()
    {
        $user = $this->actingAsUserWithCompany();
        $companyId = $user->company_id;

        $customer = Customer::factory()->create([
            'company_id' => $companyId,
            'status' => '1',
        ]);

        $doctor = Doctor::factory()->create([
            'company_id' => $companyId,
            'is_active' => true,
        ]);

        $appointment = Appointment::create([
            'company_id' => $companyId,
            'patient_id' => $customer->id,
            'doctor_id' => $doctor->id,
            'doctor_name' => $doctor->name ?? 'Doctor',
            'appointment_date' => '2026-03-05',
            'appointment_time' => '10:00',
            'status' => 'scheduled',
            'notes' => null,
            'created_by' => $user->id,
        ]);

        $procedure = Procedure::create([
            'company_id' => $companyId,
            'name' => 'Filling',
            'default_price' => 250,
            'is_active' => true,
        ]);

        $create = $this->postJson('/api/erp/dental-records', [
            'customer_id' => $customer->id,
            'appointment_id' => $appointment->id,
            'procedure_id' => $procedure->id,
            'tooth_number' => '16',
            'surface' => 'occlusal',
            'status' => 'planned',
            'notes' => 'Initial chart record',
        ]);

        $create->assertStatus(201);

        $recordId = $create->json('data.id');

        $this->assertDatabaseHas('dental_records', [
            'id' => $recordId,
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'appointment_id' => $appointment->id,
            'procedure_id' => $procedure->id,
            'tooth_number' => '16',
            'status' => 'planned',
        ]);

        $show = $this->getJson("/api/erp/dental-records/{$recordId}");
        $show->assertStatus(200);

        $update = $this->putJson("/api/erp/dental-records/{$recordId}", [
            'status' => 'completed',
            'notes' => 'Done',
        ]);

        $update->assertStatus(200);

        $this->assertDatabaseHas('dental_records', [
            'id' => $recordId,
            'status' => 'completed',
            'notes' => 'Done',
        ]);

        $delete = $this->deleteJson("/api/erp/dental-records/{$recordId}");
        $delete->assertStatus(200);

        $this->assertDatabaseMissing('dental_records', [
            'id' => $recordId,
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
