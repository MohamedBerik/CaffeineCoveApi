<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function indexErp(Request $request)
    {
        $companyId = $request->user()->company_id;

        $invoices = Invoice::with('customer')
            ->where('company_id', $companyId)
            ->orderByDesc('issued_at')
            ->get()
            ->map(fn($invoice) => $this->buildInvoiceResponse($invoice, $companyId));

        return response()->json($invoices);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $invoice = Invoice::with([
            'customer',
            'items.product',
            'payments.refunds'
        ])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json(
            $this->buildInvoiceResponse($invoice, $companyId)
        );
    }

    public function showFullInvoice(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $invoice = Invoice::with([
            'customer',
            'items.product',
            'payments.refunds',
            'journalEntries.lines.account'
        ])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json(
            $this->buildInvoiceResponse($invoice, $companyId)
        );
    }

    /**
     * 🔥 القلب المحاسبي — الحساب الموحد لكل endpoints
     */
    private function buildInvoiceResponse(Invoice $invoice, int $companyId): array
    {
        $payments = Payment::where('company_id', $companyId)
            ->where('invoice_id', $invoice->id)
            ->with(['refunds' => fn($q) => $q->where('company_id', $companyId)])
            ->orderBy('id')
            ->get();

        // إجمالي المدفوع (full payments)
        $totalPaid = $payments->sum('amount');

        // إجمالي المرتجع (من payment_refunds)
        $totalRefunded = DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->where('payments.company_id', $companyId)
            ->where('payment_refunds.company_id', $companyId)
            ->where('payments.invoice_id', $invoice->id)
            ->sum('payment_refunds.amount'); // ✅ fully qualified

        $netPaid = $totalPaid - $totalRefunded;

        $remaining = max(0, (float)$invoice->total - (float)$netPaid);

        return [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'issued_at' => $invoice->issued_at,
                'total' => $invoice->total,
                'status' => $invoice->status,
                'customer' => $invoice->customer,
                'items' => $invoice->items ?? [],
                'journal_entries' => $invoice->journalEntries ?? [],
            ],

            // computed
            'total_paid' => (float)$totalPaid,
            'total_refunded' => (float)$totalRefunded,
            'net_paid' => (float)$netPaid,
            'remaining' => (float)$remaining,

            'payments' => $payments->map(function ($p) {

                $refundedInvoice = $p->refunds
                    ->where('applies_to', 'invoice')
                    ->sum('amount');

                $refundedCredit = $p->refunds
                    ->where('applies_to', 'credit')
                    ->sum('amount');

                return [
                    'id' => $p->id,
                    'amount' => (float)$p->amount,
                    'applied_amount' => (float)$p->applied_amount,
                    'credit_amount' => (float)$p->credit_amount,
                    'method' => $p->method,
                    'paid_at' => $p->paid_at,

                    'refunded_invoice' => (float)$refundedInvoice,
                    'refunded_credit' => (float)$refundedCredit,

                    'available_invoice_refund' =>
                    max(0, (float)$p->applied_amount - (float)$refundedInvoice),

                    'available_credit_refund' =>
                    max(0, (float)$p->credit_amount - (float)$refundedCredit),
                ];
            })->values(),
        ];
    }
}
