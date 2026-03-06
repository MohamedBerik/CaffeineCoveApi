<?php

namespace Tests\Feature\Erp;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Carbon;

use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Doctor;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class ErpDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_erp_dashboard_scoped_to_company()
    {
        $user = $this->actingAsUserWithCompany();
        $companyId = $user->company_id;

        $today = Carbon::today()->toDateString();

        $customer = Customer::factory()->create([
            'company_id' => $companyId,
            'status' => '1',
        ]);

        $doctor = Doctor::factory()->create([
            'company_id' => $companyId,
            'is_active' => true,
        ]);

        Appointment::create([
            'company_id' => $companyId,
            'patient_id' => $customer->id,
            'doctor_id' => $doctor->id,
            'doctor_name' => $doctor->name ?? 'Doctor',
            'appointment_date' => $today,
            'appointment_time' => '10:00',
            'status' => 'scheduled',
            'notes' => null,
            'created_by' => $user->id,
        ]);

        Appointment::create([
            'company_id' => $companyId,
            'patient_id' => $customer->id,
            'doctor_id' => $doctor->id,
            'doctor_name' => $doctor->name ?? 'Doctor',
            'appointment_date' => $today,
            'appointment_time' => '11:00',
            'status' => 'completed',
            'notes' => null,
            'created_by' => $user->id,
        ]);

        $order = Order::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'title_en' => 'Services',
            'title_ar' => 'خدمات',
            'description_en' => 'Dashboard order',
            'description_ar' => 'طلب لوحة التحكم',
            'status' => 'confirmed',
            'total' => 300,
            'created_by' => $user->id,
        ]);

        $invoiceUnpaid = Invoice::create([
            'company_id' => $companyId,
            'number' => 'INV-DASH-1',
            'order_id' => $order->id,
            'appointment_id' => null,
            'treatment_plan_id' => null,
            'customer_id' => $customer->id,
            'total' => 300,
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);

        $invoicePartial = Invoice::create([
            'company_id' => $companyId,
            'number' => 'INV-DASH-2',
            'order_id' => $order->id,
            'appointment_id' => null,
            'treatment_plan_id' => null,
            'customer_id' => $customer->id,
            'total' => 500,
            'status' => 'partially_paid',
            'issued_at' => now(),
        ]);

        $paidAt = Carbon::today()->setTime(12, 0, 0);

        $paymentId = DB::table('payments')->insertGetId([
            'company_id'     => $companyId,
            'invoice_id'     => $invoicePartial->id,
            'amount'         => 200,
            'applied_amount' => 200,
            'credit_amount'  => 0,
            'method'         => 'cash',
            'paid_at'        => $paidAt,
            'received_by'    => $user->id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $payment = Payment::findOrFail($paymentId);

        // customer credit wallet
        DB::table('customer_credits')->insert([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'invoice_id' => null,
            'payment_id' => $payment->id,
            'refund_id' => null,
            'type' => 'credit',
            'amount' => 50,
            'entry_date' => now()->toDateString(),
            'description' => 'Dashboard test credit',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->getJson('/api/erp/dashboard');

        $res->assertStatus(200);

        $res->assertJsonPath('data.kpis.today_appointments_count', 2);
        $res->assertJsonPath('data.kpis.scheduled_today_count', 1);
        $res->assertJsonPath('data.kpis.completed_today_count', 1);
        $res->assertJsonPath('data.kpis.unpaid_invoices_count', 1);
        $res->assertJsonPath('data.kpis.partially_paid_invoices_count', 1);
        $res->assertJsonPath('data.kpis.today_revenue', 200);
        $res->assertJsonPath('data.kpis.credit_balance_total', 50);

        $this->assertNotEmpty($res->json('data.recent_appointments'));
        $this->assertNotEmpty($res->json('data.recent_invoices'));
        $this->assertNotEmpty($res->json('data.recent_payments'));
    }

    public function test_other_company_data_is_not_included_in_dashboard()
    {
        $user = $this->actingAsUserWithCompany();
        $companyId = $user->company_id;

        $otherCompany = Company::factory()->create();

        $otherCustomer = Customer::factory()->create([
            'company_id' => $otherCompany->id,
            'status' => '1',
        ]);

        $otherDoctor = Doctor::factory()->create([
            'company_id' => $otherCompany->id,
            'is_active' => true,
        ]);

        Appointment::create([
            'company_id' => $otherCompany->id,
            'patient_id' => $otherCustomer->id,
            'doctor_id' => $otherDoctor->id,
            'doctor_name' => $otherDoctor->name ?? 'Doctor',
            'appointment_date' => Carbon::today()->toDateString(),
            'appointment_time' => '09:00',
            'status' => 'scheduled',
            'notes' => null,
            'created_by' => $user->id,
        ]);

        $res = $this->getJson('/api/erp/dashboard');

        $res->assertStatus(200);
        $res->assertJsonPath('data.kpis.today_appointments_count', 0);
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
