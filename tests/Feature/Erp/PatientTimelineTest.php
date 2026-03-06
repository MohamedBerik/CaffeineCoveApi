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
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Account;

class PatientTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_patient_timeline_scoped_to_company()
    {
        $user = $this->actingAsUserWithCompany();
        $companyId = $user->company_id;

        $this->seedBasicAccounts($companyId);

        $customer = Customer::factory()->create([
            'company_id' => $companyId,
            'status' => '1',
            'name' => 'Patient A',
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

        DentalRecord::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'appointment_id' => $appointment->id,
            'procedure_id' => $procedure->id,
            'tooth_number' => '16',
            'surface' => 'occlusal',
            'status' => 'planned',
            'notes' => 'Initial note',
        ]);

        $order = Order::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'title_en' => 'Services',
            'title_ar' => 'خدمات',
            'description_en' => 'Order for timeline',
            'description_ar' => 'طلب للتسلسل الزمني',
            'status' => 'confirmed',
            'total' => 250,
            'created_by' => $user->id,
        ]);

        $invoice = Invoice::create([
            'company_id' => $companyId,
            'number' => 'INV-TIME-1',
            'order_id' => $order->id,
            'appointment_id' => $appointment->id,
            'treatment_plan_id' => null,
            'customer_id' => $customer->id,
            'total' => 250,
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);

        $payment = Payment::create([
            'company_id' => $companyId,
            'invoice_id' => $invoice->id,
            'amount' => 250,
            'method' => 'cash',
            'paid_at' => now(),
            'received_by' => $user->id,
        ]);

        $payment->forceFill([
            'applied_amount' => 250,
            'credit_amount' => 0,
        ])->save();

        $res = $this->getJson("/api/erp/customers/{$customer->id}/timeline");

        $res->assertStatus(200);

        $timeline = $res->json('data.timeline');

        $this->assertNotEmpty($timeline);

        $types = collect($timeline)->pluck('type')->all();

        $this->assertContains('appointment', $types);
        $this->assertContains('dental_record', $types);
        $this->assertContains('invoice', $types);
        $this->assertContains('payment', $types);
    }

    public function test_cannot_view_other_company_patient_timeline()
    {
        $user = $this->actingAsUserWithCompany();

        $otherCompany = Company::factory()->create();

        $otherCustomer = Customer::factory()->create([
            'company_id' => $otherCompany->id,
            'status' => '1',
        ]);

        $res = $this->getJson("/api/erp/customers/{$otherCustomer->id}/timeline");

        $res->assertStatus(404);
    }

    private function seedBasicAccounts(int $companyId): void
    {
        foreach (
            [
                ['code' => '1000', 'name' => 'Cash'],
                ['code' => '1100', 'name' => 'Accounts Receivable'],
                ['code' => '2100', 'name' => 'Customer Credits'],
            ] as $a
        ) {
            Account::firstOrCreate(
                ['company_id' => $companyId, 'code' => $a['code']],
                ['name' => $a['name']]
            );
        }
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
