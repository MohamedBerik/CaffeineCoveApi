<?php

namespace Tests\Feature\Erp;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use App\Models\User;
use App\Models\Company;
use App\Models\Doctor;
use App\Models\Customer;
use App\Models\Appointment;

class AppointmentCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_cancel_scheduled_appointment(): void
    {
        $user = $this->actingAsAdminWithCompany();
        $companyId = $user->company_id;

        $doctor = Doctor::factory()->create([
            'company_id' => $companyId,
            'is_active' => true,
            'work_start' => '09:00',
            'work_end' => '17:00',
            'slot_minutes' => 30,
        ]);

        $patient = Customer::factory()->create([
            'company_id' => $companyId,
        ]);

        $appointment = Appointment::factory()->create([
            'company_id' => $companyId,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'doctor_name' => $doctor->name ?? 'Doctor',
            'appointment_date' => '2026-03-05',
            'appointment_time' => '10:00',
            'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        $response = $this->postJson("/api/erp/appointments/{$appointment->id}/cancel");

        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'company_id' => $companyId,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_cancel_completed_appointment(): void
    {
        $user = $this->actingAsAdminWithCompany();
        $companyId = $user->company_id;

        $doctor = Doctor::factory()->create([
            'company_id' => $companyId,
            'is_active' => true,
        ]);

        $patient = Customer::factory()->create([
            'company_id' => $companyId,
        ]);

        $appointment = Appointment::factory()->create([
            'company_id' => $companyId,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'doctor_name' => $doctor->name ?? 'Doctor',
            'appointment_date' => '2026-03-05',
            'appointment_time' => '10:00',
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        $response = $this->postJson("/api/erp/appointments/{$appointment->id}/cancel");

        // أنت بتعمل throw ValidationException -> عادة بيرجع 422
        $response->assertStatus(422);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'company_id' => $companyId,
            'status' => 'completed',
        ]);
    }

    private function actingAsAdminWithCompany(): User
    {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'admin',        // ✅ يخلّي hasPermission = true عندك
            'is_super_admin' => false,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }
}
