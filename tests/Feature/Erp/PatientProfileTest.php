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
use App\Models\TreatmentPlan;
use App\Models\DentalRecord;
use App\Models\Invoice;
use App\Models\Order;

class PatientProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_patient_profile_scoped_to_company()
    {
        $user = $this->actingAsUserWithCompany();
        $companyId = $user->company_id;

        $customer = Customer::factory()->create([
            'company_id' => $companyId,
            'status' => '1',
            'name' => 'Patient A',
            'email' => 'patient@example.com',
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
            'appointment_date' => '2026-03-06',
            'appointment_time' => '10:00',
            'status' => 'scheduled',
            'notes' => 'checkup',
            'created_by' => $user->id,
        ]);

        $procedure = Procedure::create([
            'company_id' => $companyId,
            'name' => 'Filling',
            'default_price' => 250,
            'is_active' => true,
        ]);

        $record = DentalRecord::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'appointment_id' => $appointment->id,
            'procedure_id' => $procedure->id,
            'tooth_number' => '16',
            'surface' => 'occlusal',
            'status' => 'planned',
            'notes' => 'Initial note',
        ]);

        $plan = TreatmentPlan::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'title' => 'Plan A',
            'notes' => null,
            'total_cost' => 1000,
            'status' => 'active',
        ]);

        \App\Models\TreatmentPlanItem::create([
            'company_id' => $companyId,
            'treatment_plan_id' => $plan->id,
            'procedure_id' => $procedure->id,
            'procedure' => 'Filling',
            'tooth_number' => '16',
            'surface' => 'occlusal',
            'notes' => null,
            'price' => 250,
        ]);

        $order = Order::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'title_en' => 'Services',
            'title_ar' => 'خدمات',
            'description_en' => 'Order for profile',
            'description_ar' => 'طلب لملف المريض',
            'status' => 'confirmed',
            'total' => 250,
            'created_by' => $user->id,
        ]);

        $invoice = Invoice::create([
            'company_id' => $companyId,
            'number' => 'INV-PROFILE-1',
            'order_id' => $order->id,
            'appointment_id' => $appointment->id,
            'treatment_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'total' => 250,
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);

        $res = $this->getJson("/api/erp/customers/{$customer->id}/profile");

        $res->assertStatus(200);

        $res->assertJsonPath('data.patient.id', $customer->id);
        $res->assertJsonPath('data.patient.name', 'Patient A');

        $this->assertNotEmpty($res->json('data.appointments'));
        $this->assertNotEmpty($res->json('data.dental_records'));
        $this->assertNotEmpty($res->json('data.treatment_plans'));
        $this->assertNotEmpty($res->json('data.invoices'));
    }

    public function test_cannot_view_other_company_patient_profile()
    {
        $user = $this->actingAsUserWithCompany();

        $otherCompany = Company::factory()->create();

        $otherCustomer = Customer::factory()->create([
            'company_id' => $otherCompany->id,
            'status' => '1',
        ]);

        $res = $this->getJson("/api/erp/customers/{$otherCustomer->id}/profile");

        $res->assertStatus(404);
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
