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
use App\Models\TreatmentPlan;

class AppointmentCompleteFromPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_complete_appointment_using_treatment_plan_items_and_create_invoice_with_order()
    {
        $user = $this->actingAsUserWithCompany();
        $companyId = $user->company_id;

        // ✅ Create category (safe required fields)
        $category = \App\Models\Category::create([
            'company_id'      => $companyId,
            'cate_image'      => null,
            'title_en'        => 'Services',
            'title_ar'        => 'الخدمات',
            'description_en'  => 'Clinic services category',
            'description_ar'  => 'تصنيف خدمات العيادة',
        ]);

        // ✅ Ensure service product exists (required by complete())
        \App\Models\Product::create([
            'company_id'      => $companyId,
            'product_image'   => null,
            'title_en'        => 'Consultation',
            'title_ar'        => 'كشف',
            'description_en'  => 'Consultation service',
            'description_ar'  => 'خدمة كشف',
            'unit_price'      => 0,
            'quantity'        => 0,
            'category_id'     => $category->id,
            'stock_quantity'  => 0,
            'minimum_quantity' => 0,
        ]);

        // Customer (Patient)
        $customer = Customer::factory()->create([
            'company_id' => $companyId,
            'name'       => 'Patient A',
            'email'      => 'patient@example.com',
            'status'     => '1', // ✅ matches your enum ["0","1"]
        ]);

        // Doctor
        $doctor = Doctor::factory()->create([
            'company_id'    => $companyId,
            'is_active'     => true,
            'work_start'    => '09:00',
            'work_end'      => '17:00',
            'slot_minutes'  => 30,
        ]);

        // Appointment (scheduled)
        $appointment = Appointment::create([
            'company_id'        => $companyId,
            'patient_id'        => $customer->id,
            'doctor_id'         => $doctor->id,
            'doctor_name'       => $doctor->name ?? 'Doctor',
            'appointment_date'  => '2026-03-05',
            'appointment_time'  => '10:00',
            'status'            => 'scheduled',
            'notes'             => null,
            'created_by'        => $user->id,
        ]);

        // Treatment Plan
        $plan = TreatmentPlan::create([
            'company_id'  => $companyId,
            'customer_id' => $customer->id,
            'title'       => 'Dental Plan A',
            'notes'       => null,
            'total_cost'  => 1000,
            'status'      => 'active',
        ]);

        // Treatment plan items (sum = 700)
        \App\Models\TreatmentPlanItem::create([
            'company_id'        => $companyId,
            'treatment_plan_id' => $plan->id,
            'procedure'         => 'Filling',
            'tooth_number'      => '16',
            'surface'           => 'occlusal',
            'notes'             => null,
            'price'             => 250,
        ]);

        \App\Models\TreatmentPlanItem::create([
            'company_id'        => $companyId,
            'treatment_plan_id' => $plan->id,
            'procedure'         => 'Root canal',
            'tooth_number'      => '11',
            'surface'           => null,
            'notes'             => null,
            'price'             => 450,
        ]);

        $expectedTotal = 250 + 450;

        // Call complete with treatment_plan_id (total should be computed from items)
        $res = $this->postJson("/api/erp/appointments/{$appointment->id}/complete", [
            'treatment_plan_id' => $plan->id,
            'doctor_name'       => 'Dr. Test',
            'notes'             => 'Completed from plan',
            // 'total' intentionally omitted (we want controller to compute from plan items)
        ]);

        // لو حصل 422/500 اطبع السبب بسرعة:
        // $res->dump();

        $res->assertStatus(200);

        // Appointment status should become completed
        $this->assertDatabaseHas('appointments', [
            'id'         => $appointment->id,
            'company_id' => $companyId,
            'status'     => 'completed',
        ]);

        // Invoice should be created and linked correctly
        $invoiceId = $res->json('invoice_id');
        $this->assertNotEmpty($invoiceId);

        $this->assertDatabaseHas('invoices', [
            'id'               => $invoiceId,
            'company_id'       => $companyId,
            'appointment_id'   => $appointment->id,
            'treatment_plan_id' => $plan->id,
            'customer_id'      => $customer->id,
            'status'           => 'unpaid',
        ]);

        // ✅ critical: order_id is required
        $invoiceRow = \App\Models\Invoice::find($invoiceId);
        $this->assertNotNull($invoiceRow->order_id);

        // total should equal sum of plan items
        $this->assertEquals($expectedTotal, (float) $invoiceRow->total);

        // Order should exist and match customer
        $this->assertDatabaseHas('orders', [
            'id'         => $invoiceRow->order_id,
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'status'     => 'confirmed',
        ]);

        // Ledger entry should exist (invoice debit)
        $this->assertDatabaseHas('customer_ledger_entries', [
            'company_id'  => $companyId,
            'customer_id' => $customer->id,
            'invoice_id'  => $invoiceId,
            'type'        => 'invoice',
        ]);
    }

    private function actingAsUserWithCompany(): User
    {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'company_id'      => $company->id,
            'role'            => 'admin',
            'is_super_admin'  => 0,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }
}
