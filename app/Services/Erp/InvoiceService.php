<?php

namespace App\Services\Erp;

use App\Events\DashboardUpdated;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function indexErp(Request $request)
    {
        return Invoice::with('customer')
            ->orderByDesc('issued_at')
            ->get()
            ->map(fn($invoice) => $this->buildInvoiceResponse($invoice));
    }

    public function show(Request $request, $id)
    {
        $invoice = Invoice::with([
            'customer',
            'items.product',
        ])->findOrFail($id);

        return $this->buildInvoiceResponse($invoice);
    }

    public function showFullInvoice(Request $request, $id)
    {
        $invoice = Invoice::with([
            'customer',
            'items.product',
            'journalEntries' => function ($q) {
                $q->orderBy('id')
                    ->with([
                        'lines' => function ($q2) {
                            $q2->orderBy('id')
                                ->with(['account']);
                        }
                    ]);
            },
        ])->findOrFail($id);

        return $this->buildInvoiceResponse($invoice);
    }

    public function buildInvoiceResponse(Invoice $invoice): array
    {
        $companyId = Tenant::id();

        $payments = Payment::withoutGlobalScope(\App\Models\Concerns\BranchScope::class)
            ->where('invoice_id', $invoice->id)
            ->select([
                'id',
                'company_id',
                'invoice_id',
                'amount',
                'applied_amount',
                'credit_amount',
                'method',
                'paid_at',
                'received_by',
                'created_at',
            ])
            ->with(['refunds' => function ($q) {
                $q->select([
                    'id',
                    'company_id',
                    'payment_id',
                    'amount',
                    'applies_to',
                    'refunded_at',
                    'created_at',
                ])->orderBy('id');
            }])
            ->orderBy('id')
            ->get();

        $invoice->setRelation('payments', $payments);

        $totalApplied = (float) $payments->sum(fn($p) => (float) $p->applied_amount);

        $totalRefundedInvoice = (float) $payments->sum(function ($p) {
            return (float) $p->refunds->where('applies_to', 'invoice')->sum('amount');
        });

        $totalCreditAppliedToThisInvoice = (float) DB::table('customer_credits')
            ->where('invoice_id', $invoice->id)
            ->where('type', 'debit')
            ->sum('amount');

        $netPaid = $totalApplied - $totalRefundedInvoice + $totalCreditAppliedToThisInvoice;
        $remaining = max(0, (float) $invoice->total - $netPaid);

        $totalCustomerCreditIssued = (float) DB::table('customer_credits')
            ->where('customer_id', $invoice->customer_id)
            ->where('type', 'credit')
            ->sum('amount');

        $totalCustomerCreditUsed = (float) DB::table('customer_credits')
            ->where('customer_id', $invoice->customer_id)
            ->where('type', 'debit')
            ->sum('amount');

        $customerCreditBalance = max(0, $totalCustomerCreditIssued - $totalCustomerCreditUsed);

        $totalCreditIssuedFromThisInvoice = (float) $payments->sum(fn($p) => (float) $p->credit_amount);

        $totalRefundedCreditFromThisInvoice = (float) $payments->sum(function ($p) {
            return (float) $p->refunds->where('applies_to', 'credit')->sum('amount');
        });

        $netCreditFromThisInvoice = $totalCreditIssuedFromThisInvoice - $totalRefundedCreditFromThisInvoice;

        $totalCashReceived = (float) $payments->sum(fn($p) => (float) $p->amount);

        // if ($invoice->status === 'paid') {
        //     event(new DashboardUpdated($companyId, 'invoice_paid', [
        //         'paid_invoices_count' => 1,
        //         'unpaid_invoices_count' => -1,
        //     ]));
        // }

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'issued_at' => $invoice->issued_at,
            'total' => (float) $invoice->total,
            'status' => $invoice->status,
            'appointment_id' => $invoice->appointment_id,
            'order_id' => $invoice->order_id,
            'treatment_plan_id' => $invoice->treatment_plan_id,
            'customer' => $invoice->customer,
            'customer_id' => $invoice->customer_id,

            'items' => $invoice->items ?? [],
            'journal_entries' => $invoice->journalEntries ?? [],

            'total_paid' => (float) $totalApplied,
            'total_refunded' => (float) $totalRefundedInvoice,
            'total_credit_applied' => (float) $totalCreditAppliedToThisInvoice,
            'net_paid' => (float) $netPaid,
            'remaining' => (float) $remaining,

            'credit_issued' => (float) $totalCreditIssuedFromThisInvoice,
            'credit_refunded' => (float) $totalRefundedCreditFromThisInvoice,
            'net_credit' => (float) $netCreditFromThisInvoice,

            'customer_credit_issued_total' => (float) $totalCustomerCreditIssued,
            'customer_credit_used_total' => (float) $totalCustomerCreditUsed,
            'customer_credit_balance' => (float) $customerCreditBalance,

            'cash_received' => (float) $totalCashReceived,

            'payments' => $payments->map(function ($p) {
                $refInv = (float) $p->refunds->where('applies_to', 'invoice')->sum('amount');
                $refCr  = (float) $p->refunds->where('applies_to', 'credit')->sum('amount');

                return [
                    'id' => $p->id,
                    'amount' => (float) $p->amount,
                    'applied_amount' => (float) $p->applied_amount,
                    'credit_amount' => (float) $p->credit_amount,
                    'method' => $p->method,
                    'paid_at' => $p->paid_at,
                    'received_by' => $p->received_by,
                    'created_at' => $p->created_at,

                    'refunded_invoice' => $refInv,
                    'refunded_credit' => $refCr,
                    'available_invoice_refund' => max(0, (float) $p->applied_amount - $refInv),
                    'available_credit_refund' => max(0, (float) $p->credit_amount - $refCr),

                    'refunds' => $p->refunds->values(),
                ];
            })->values(),
        ];
    }
}
