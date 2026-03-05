<?php

namespace Tests\Feature\Erp;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Account;

class PaymentRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_refund_invoice_payment()
    {
        $user = $this->actingAsUser();
        $companyId = $user->company_id;

        $this->seedBasicAccounts($companyId);

        $customer = Customer::factory()->create([
            'company_id' => $companyId,
            'status' => '1',
        ]);

        // ✅ Create order first (because invoices.order_id FK)
        $order = Order::create([
            'company_id'  => $companyId,
            'customer_id' => $customer->id,
            'status'      => 'confirmed',
            'total'       => 500,
            'created_by'  => $user->id,
            // لو الأعمدة دي required عندك خليها موجودة
            'title_en'    => 'Services',
            'title_ar'    => 'خدمات',
            'description_en' => 'Order for invoice',
            'description_ar' => 'طلب فاتورة',
        ]);

        $invoice = Invoice::create([
            'company_id'        => $companyId,
            'number'            => 'INV-TEST-1',
            'order_id'          => $order->id,
            'appointment_id'    => null,
            'treatment_plan_id' => null,
            'customer_id'       => $customer->id,
            'total'             => 500,
            'status'            => 'paid',
            'issued_at'         => now(),
        ]);

        $payment = Payment::create([
            'company_id'  => $companyId,
            'invoice_id'  => $invoice->id,
            'amount'      => 500,
            'method'      => 'cash',
            'paid_at'     => now(),
            'received_by' => $user->id,
        ]);

        // ✅ IMPORTANT: ensure these columns are actually saved (fillable/guarded issue)
        $payment->forceFill([
            'applied_amount' => 500,
            'credit_amount'  => 0,
        ])->save();

        $payment->refresh();

        $res = $this->postJson("/api/erp/payments/{$payment->id}/refund", [
            'amount'     => 200,
            'applies_to' => 'invoice',
        ]);

        $res->assertStatus(200);

        $this->assertDatabaseHas('payment_refunds', [
            'company_id' => $companyId,
            'payment_id' => $payment->id,
            'applies_to' => 'invoice',
            'amount'     => 200,
        ]);

        $this->assertDatabaseHas('customer_ledger_entries', [
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'payment_id' => $payment->id,
            'type'       => 'refund_invoice',
            'debit'      => 200,
        ]);
    }

    public function test_cannot_refund_more_than_available()
    {
        $user = $this->actingAsUser();
        $companyId = $user->company_id;

        $this->seedBasicAccounts($companyId);

        $customer = Customer::factory()->create([
            'company_id' => $companyId,
            'status' => '1',
        ]);

        $order = Order::create([
            'company_id'  => $companyId,
            'customer_id' => $customer->id,
            'status'      => 'confirmed',
            'total'       => 500,
            'created_by'  => $user->id,
            'title_en'    => 'Services',
            'title_ar'    => 'خدمات',
            'description_en' => 'Order for invoice',
            'description_ar' => 'طلب فاتورة',
        ]);

        $invoice = Invoice::create([
            'company_id'        => $companyId,
            'number'            => 'INV-TEST-2',
            'order_id'          => $order->id,
            'appointment_id'    => null,
            'treatment_plan_id' => null,
            'customer_id'       => $customer->id,
            'total'             => 500,
            'status'            => 'paid',
            'issued_at'         => now(),
        ]);

        $payment = Payment::create([
            'company_id'  => $companyId,
            'invoice_id'  => $invoice->id,
            'amount'      => 500,
            'method'      => 'cash',
            'paid_at'     => now(),
            'received_by' => $user->id,
        ]);

        // ✅ IMPORTANT: ensure these columns are actually saved (fillable/guarded issue)
        $payment->forceFill([
            'applied_amount' => 500,
            'credit_amount'  => 0,
        ])->save();

        $payment->refresh();

        $res = $this->postJson("/api/erp/payments/{$payment->id}/refund", [
            'amount'     => 600,
            'applies_to' => 'invoice',
        ]);

        $res->assertStatus(422);
    }

    private function seedBasicAccounts(int $companyId): void
    {
        // codes used in controllers: 1000 cash, 1100 AR, 2100 customer credit
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

    private function actingAsUser(): User
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
