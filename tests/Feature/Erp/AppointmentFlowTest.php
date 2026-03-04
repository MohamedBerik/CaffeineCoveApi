<?php

namespace Tests\Feature\Erp;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use App\Http\Middleware\CheckPermission;
use App\Models\User;
use App\Models\Company;
use App\Models\Doctor;
use App\Models\Customer;

class AppointmentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_example()
    {
        $this->assertTrue(true);
    }

    public function test_can_book_appointment()
    {
        // ✅ We are testing booking flow, not permissions here
        $this->withoutMiddleware(CheckPermission::class);

        $user = $this->actingAsUserWithCompany();
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

        $response = $this->postJson('/api/erp/appointments/book', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => '2026-03-05',
            'appointment_time' => '10:00',
            'notes' => 'test booking',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('appointments', [
            'company_id' => $companyId,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => '2026-03-05',
            'appointment_time' => '10:00:00', // MySQL may store time with seconds
            'status' => 'scheduled',
        ]);
    }

    private function actingAsUserWithCompany(): User
    {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }
}
