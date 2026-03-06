<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\DentalRecord;
use App\Models\Invoice;
use App\Models\TreatmentPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientProfileController extends Controller
{
    public function show(Request $request, $customerId)
    {
        $companyId = $request->user()->company_id;

        $customer = Customer::query()
            ->where('company_id', $companyId)
            ->findOrFail($customerId);

        $appointments = Appointment::query()
            ->where('company_id', $companyId)
            ->where('patient_id', $customer->id)
            ->with([
                'doctor:id,name,company_id',
            ])
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->limit(10)
            ->get();

        $dentalRecords = DentalRecord::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->with([
                'appointment:id,company_id,appointment_date,appointment_time,status',
                'procedure:id,company_id,name,default_price',
            ])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $treatmentPlans = TreatmentPlan::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->with([
                'items:id,company_id,treatment_plan_id,procedure_id,procedure,tooth_number,surface,notes,price',
            ])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $invoices = Invoice::query()
            ->where('company_id', $companyId)
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

        $creditIssued = (float) DB::table('customer_credits')
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->where('type', 'credit')
            ->sum('amount');

        $creditUsed = (float) DB::table('customer_credits')
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->where('type', 'debit')
            ->sum('amount');

        $netCredit = $creditIssued - $creditUsed;

        $ledger = DB::table('customer_ledger_entries')
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id);

        $openingBalance = 0.0;
        $totalDebit = (float) (clone $ledger)->sum('debit');
        $totalCredit = (float) (clone $ledger)->sum('credit');
        $closingBalance = $openingBalance + ($totalDebit - $totalCredit);

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
                'appointments' => $appointments,
                'dental_records' => $dentalRecords,
                'treatment_plans' => $treatmentPlans,
                'invoices' => $invoices,
                'credit_balance' => [
                    'credit_issued' => $creditIssued,
                    'credit_used' => $creditUsed,
                    'net_credit' => $netCredit,
                ],
                'statement_summary' => [
                    'opening_balance' => $openingBalance,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'closing_balance' => $closingBalance,
                ],
            ],
        ]);
    }
}
