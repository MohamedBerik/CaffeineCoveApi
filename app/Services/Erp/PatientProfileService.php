<?php

namespace App\Services\Erp;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\DentalRecord;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Procedure;
use App\Models\TreatmentPlan;
use Illuminate\Support\Facades\DB;

class PatientProfileService
{
    public function build(Customer $customer): array
    {
        return [
            'patient' => $customer,

            'procedures' => $this->getProcedures(),

            'appointments' => $this->getAppointments($customer),

            'dental_records' => $this->getDentalRecords($customer),

            'treatment_plans' => $this->getTreatmentPlans($customer),

            'invoices' => $this->getInvoices($customer),

            'financial_summary' => $this->getFinancialSummary($customer),
        ];
    }

    protected function getAppointments(Customer $customer)
    {
        return Appointment::query()
            ->where('patient_id', $customer->id)
            ->with([
                'doctor:id,name,company_id',
            ])
            ->latest('appointment_date')
            ->latest('appointment_time')
            ->limit(10)
            ->get();
    }

    protected function getDentalRecords(Customer $customer)
    {
        return DentalRecord::query()
            ->where('customer_id', $customer->id)
            ->with([
                'appointment:id,company_id,appointment_date,appointment_time,status',
                'procedure:id,company_id,name,default_price',
                'treatmentPlanItem',
            ])
            ->latest('id')
            ->limit(100)
            ->get();
    }

    protected function getTreatmentPlans(Customer $customer)
    {
        $plans = TreatmentPlan::query()
            ->where('customer_id', $customer->id)
            ->with([
                'items',
            ])
            ->latest('id')
            ->limit(10)
            ->get();

        $plans->each(function ($plan) {
            $plan->items->transform(function ($item) {

                $completedSessions = (int) $item->completed_sessions;
                $plannedSessions = max(1, (int) $item->planned_sessions);

                $remainingSessions = max(
                    0,
                    $plannedSessions - $completedSessions
                );

                $progress = min(
                    100,
                    round(($completedSessions / $plannedSessions) * 100)
                );

                $item->remaining_sessions = $remainingSessions;
                $item->progress_percentage = $progress;
                $item->is_completed = $remainingSessions === 0;

                return $item;
            });
        });

        return $plans;
    }

    protected function getInvoices(Customer $customer)
    {
        return Invoice::query()
            ->where('customer_id', $customer->id)
            ->latest('issued_at')
            ->latest('id')
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
    }

    protected function getFinancialSummary(Customer $customer): array
    {
        $invoiceIds = Invoice::query()
            ->where('customer_id', $customer->id)
            ->pluck('id');

        $creditIssued = (float) DB::table('customer_credits')
            ->where('customer_id', $customer->id)
            ->where('type', 'credit')
            ->sum('amount');

        $creditUsed = (float) DB::table('customer_credits')
            ->where('customer_id', $customer->id)
            ->where('type', 'debit')
            ->sum('amount');

        $netCredit = max(0, $creditIssued - $creditUsed);

        $invoicesTotal = (float) Invoice::query()
            ->where('customer_id', $customer->id)
            ->sum('total');

        $directPayments = 0.0;
        $invoiceRefunds = 0.0;
        $creditApplied = 0.0;

        if ($invoiceIds->isNotEmpty()) {

            $directPayments = (float) Payment::query()
                ->whereIn('invoice_id', $invoiceIds)
                ->sum('applied_amount');

            $invoiceRefunds = (float) DB::table('payment_refunds')
                ->join(
                    'payments',
                    'payments.id',
                    '=',
                    'payment_refunds.payment_id'
                )
                ->whereIn('payments.invoice_id', $invoiceIds)
                ->where('payment_refunds.applies_to', 'invoice')
                ->sum('payment_refunds.amount');

            $creditApplied = (float) DB::table('customer_credits')
                ->where('customer_id', $customer->id)
                ->whereIn('invoice_id', $invoiceIds)
                ->where('type', 'debit')
                ->sum('amount');
        }

        $paid = max(
            0,
            $directPayments - $invoiceRefunds + $creditApplied
        );

        $remaining = max(
            0,
            $invoicesTotal - $paid
        );

        /*
       |--------------------------------------------------------------------------
       | Ledger = Source Of Truth
       |--------------------------------------------------------------------------
       */

        $ledger = DB::table('customer_ledger_entries')
            ->where('customer_id', $customer->id);

        $totalDebit = (float) (clone $ledger)->sum('debit');

        $totalCredit = (float) (clone $ledger)->sum('credit');

        $closingBalance = $totalDebit - $totalCredit;

        return [
            'credit_balance' => [
                'credit_issued' => $creditIssued,
                'credit_used' => $creditUsed,
                'net_credit' => $netCredit,
            ],

            'invoices' => [
                'total' => $invoicesTotal,
                'direct_paid' => $directPayments,
                'credit_applied' => $creditApplied,
                'paid' => $paid,
                'remaining' => $remaining,
            ],

            'ledger' => [
                'opening_balance' => 0,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'closing_balance' => $closingBalance,
            ],
        ];
    }

    protected function getProcedures()
    {
        return Procedure::withoutGlobalScope(
            \App\Models\Concerns\BranchScope::class
        )
            ->orderBy('name')
            ->get([
                'id',
                'company_id',
                'name',
                'default_price',
            ]);
    }
}
