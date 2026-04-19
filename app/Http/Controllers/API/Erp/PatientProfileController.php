<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\DentalRecord;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Procedure;
use App\Models\TreatmentPlan;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientProfileController extends Controller
{
    public function show(Request $request, $customerId)
    {
        $companyId = Tenant::id();

        $customer = Customer::query()->findOrFail($customerId);
        $this->authorize('view', $customer);

        $appointments = Appointment::query()
            ->where('patient_id', $customer->id)
            ->with([
                'doctor:id,name,company_id',
            ])
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->limit(10)
            ->get();

        $dentalRecords = DentalRecord::query()
            ->where('customer_id', $customer->id)
            ->with([
                'appointment:id,company_id,appointment_date,appointment_time,status',
                'procedure:id,company_id,name,default_price',
                'treatmentPlanItem:id,treatment_plan_id,appointment_id,procedure_id,tooth_number,surface,status,completed_sessions,planned_sessions',
            ])
            ->orderByDesc('id')
            ->get();

        $treatmentPlans = TreatmentPlan::query()
            ->where('customer_id', $customer->id)
            ->with([
                'items:id,company_id,treatment_plan_id,procedure_id,procedure,tooth_number,surface,notes,price',
            ])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get([
                'id',
                'company_id',
                'number',
                'order_id',
                'appointment_id',
                'treatment_plan_id',
                'customer_id',
                'total',
                'status',
                'issued_at',
                'created_at',
                'updated_at',
            ]);

        // Customer Credit Balance
        $creditIssued = (float) DB::table('customer_credits')
            ->where('customer_id', $customer->id)
            ->where('type', 'credit')
            ->sum('amount');

        $creditUsed = (float) DB::table('customer_credits')
            ->where('customer_id', $customer->id)
            ->where('type', 'debit')
            ->sum('amount');

        $netCredit = max(0, $creditIssued - $creditUsed);

        // Statement Summary
        $ledger = DB::table('customer_ledger_entries')
            ->where('customer_id', $customer->id);

        $openingBalance = 0.0;
        $totalDebit = (float) (clone $ledger)->sum('debit');
        $totalCredit = (float) (clone $ledger)->sum('credit');
        $closingBalance = $openingBalance + ($totalDebit - $totalCredit);

        // Accurate Invoice Financial Summary
        $invoiceIds = Invoice::query()
            ->where('customer_id', $customer->id)
            ->pluck('id');

        $invoicesTotal = (float) Invoice::query()
            ->where('customer_id', $customer->id)
            ->sum('total');

        $totalAppliedPayments = 0.0;
        $totalInvoiceRefunds = 0.0;
        $totalCreditAppliedToInvoices = 0.0;

        if ($invoiceIds->isNotEmpty()) {
            $totalAppliedPayments = (float) Payment::query()
                ->whereIn('invoice_id', $invoiceIds)
                ->sum('applied_amount');

            $totalInvoiceRefunds = (float) DB::table('payment_refunds')
                ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
                ->whereIn('payments.invoice_id', $invoiceIds)
                ->where('payment_refunds.applies_to', 'invoice')
                ->sum('payment_refunds.amount');

            $totalCreditAppliedToInvoices = (float) DB::table('customer_credits')
                ->where('customer_id', $customer->id)
                ->whereNotNull('invoice_id')
                ->whereIn('invoice_id', $invoiceIds)
                ->where('type', 'debit')
                ->sum('amount');
        }

        $invoicesPaid = max(
            0,
            $totalAppliedPayments - $totalInvoiceRefunds + $totalCreditAppliedToInvoices
        );

        $invoicesRemaining = max(0, $invoicesTotal - $invoicesPaid);

        $procedures = Procedure::query()
            ->orderBy('name', 'asc')
            ->get(['id', 'company_id', 'name', 'default_price']);

        return response()->json([
            'msg' => 'Patient profile',
            'status' => 200,
            'data' => [
                'patient' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone ?? null,
                    'patient_code' => $customer->patient_code ?? null,
                    'date_of_birth' => $customer->date_of_birth ?? null,
                    'gender' => $customer->gender ?? null,
                    'address' => $customer->address ?? null,
                    'notes' => $customer->notes ?? null,
                    'status' => $customer->status,
                    'created_at' => $customer->created_at,
                    'updated_at' => $customer->updated_at,
                ],

                'procedures' => $procedures,

                'appointments' => $appointments,
                'dental_records' => $dentalRecords,
                'treatment_plans' => $treatmentPlans,
                'invoices' => $invoices,

                'customer_credit_balance' => (float) $netCredit,
                'invoices_total' => (float) $invoicesTotal,
                'invoices_direct_paid' => (float) $totalAppliedPayments,
                'invoices_credit_applied' => (float) $totalCreditAppliedToInvoices,
                'invoices_paid' => (float) $invoicesPaid,
                'invoices_remaining' => (float) $invoicesRemaining,

                'credit_balance' => [
                    'credit_issued' => (float) $creditIssued,
                    'credit_used' => (float) $creditUsed,
                    'net_credit' => (float) $netCredit,
                ],

                'statement_summary' => [
                    'opening_balance' => (float) $openingBalance,
                    'total_debit' => (float) $totalDebit,
                    'total_credit' => (float) $totalCredit,
                    'closing_balance' => (float) $closingBalance,
                ],
            ],
        ]);
    }
}
