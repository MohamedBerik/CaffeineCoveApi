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

        // ملاحظة: ده بيعمل N+1 لأن buildInvoiceResponse بيجيب payments لكل فاتورة.
        // لو عايز Optimized بعدين نعمل eager-load + grouping.
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

        // لا نعمل payments.refunds هنا، هنجيبهم باستعلام صريح لضمان select + company filter
        $invoice = Invoice::with([
            'customer',
            'items.product',
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
            'journalEntries' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->orderBy('id')
                    ->with([
                        'lines' => function ($q2) use ($companyId) {
                            $q2->where('company_id', $companyId)
                                ->orderBy('id')
                                ->with([
                                    'account' => function ($q3) use ($companyId) {
                                        $q3->where('company_id', $companyId);
                                    }
                                ]);
                        }
                    ]);
            },
        ])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json(
            $this->buildInvoiceResponse($invoice, $companyId)
        );
    }

    /**
     * 🔥 القلب المحاسبي — response موحّد + محاسبي صح مع Overpayment (Customer Credit)
     *
     * قواعد الحساب:
     * - total_paid (toward invoice) = SUM(applied_amount)
     * - total_refunded (invoice) = SUM(refunds.amount WHERE applies_to='invoice')
     * - net_paid = applied - refunded_invoice
     * - remaining = total - net_paid
     *
     * - credit_issued = SUM(credit_amount)
     * - refunded_credit = SUM(refunds.amount WHERE applies_to='credit')
     * - net_credit = credit_issued - refunded_credit
     */
    // private function buildInvoiceResponse(Invoice $invoice, int $companyId): array
    // {
    //     // ✅ Payments query صريح لضمان إن applied_amount/credit_amount موجودين في JSON
    //     $payments = Payment::query()
    //         ->where('company_id', $companyId)
    //         ->where('invoice_id', $invoice->id)
    //         ->select([
    //             'id',
    //             'company_id',
    //             'invoice_id',
    //             'amount',
    //             'applied_amount',
    //             'credit_amount',
    //             'method',
    //             'paid_at',
    //             'received_by',
    //             'created_at',
    //         ])
    //         ->with(['refunds' => function ($q) use ($companyId) {
    //             $q->where('company_id', $companyId)
    //                 ->select([
    //                     'id',
    //                     'company_id',
    //                     'payment_id',
    //                     'amount',
    //                     'applies_to',
    //                     'refunded_at',
    //                     'created_at',
    //                 ])
    //                 ->orderBy('id');
    //         }])
    //         ->orderBy('id')
    //         ->get();

    //     // اربطهم بالـ invoice عشان أي مكان يعتمد على relation
    //     $invoice->setRelation('payments', $payments);

    //     // ✅ حسابات invoice (Applied فقط)
    //     $totalApplied = $payments->sum(fn($p) => (float) $p->applied_amount);

    //     $totalRefundedInvoice = $payments->sum(function ($p) {
    //         return (float) $p->refunds->where('applies_to', 'invoice')->sum('amount');
    //     });

    //     $netPaid = $totalApplied - $totalRefundedInvoice;
    //     $remaining = max(0, (float) $invoice->total - $netPaid);

    //     // ✅ حسابات Customer Credit
    //     $totalCreditIssued = $payments->sum(fn($p) => (float) $p->credit_amount);

    //     $totalRefundedCredit = $payments->sum(function ($p) {
    //         return (float) $p->refunds->where('applies_to', 'credit')->sum('amount');
    //     });

    //     $netCredit = $totalCreditIssued - $totalRefundedCredit;

    //     // (اختياري) لو تحب تعرض إجمالي cash دخل فعليًا:
    //     $totalCashReceived = $payments->sum(fn($p) => (float) $p->amount);

    //     return [
    //         'id' => $invoice->id,
    //         'number' => $invoice->number,
    //         'issued_at' => $invoice->issued_at,
    //         'total' => $invoice->total,
    //         'status' => $invoice->status,
    //         'appointment_id' => $invoice->appointment_id,
    //         'order_id' => $invoice->order_id,
    //         'customer' => $invoice->customer,
    //         'customer_id' => $invoice->customer_id,

    //         'items' => $invoice->items ?? [],
    //         'journal_entries' => $invoice->journalEntries ?? [],

    //         // ✅ invoice computed (صح)
    //         'total_paid' => (float) $totalApplied,              // paid toward invoice ONLY
    //         'total_refunded' => (float) $totalRefundedInvoice,  // refunded from invoice portion ONLY
    //         'net_paid' => (float) $netPaid,
    //         'remaining' => (float) $remaining,

    //         // ✅ credit computed (لو الـ UI محتاجه)
    //         'credit_issued' => (float) $totalCreditIssued,
    //         'credit_refunded' => (float) $totalRefundedCredit,
    //         'net_credit' => (float) $netCredit,

    //         // (اختياري للـ UI/Debug)
    //         'cash_received' => (float) $totalCashReceived,

    //         'payments' => $payments->map(function ($p) {
    //             $refInv = (float) $p->refunds->where('applies_to', 'invoice')->sum('amount');
    //             $refCr  = (float) $p->refunds->where('applies_to', 'credit')->sum('amount');

    //             $availableInv = max(0, (float) $p->applied_amount - $refInv);
    //             $availableCr  = max(0, (float) $p->credit_amount - $refCr);

    //             return [
    //                 'id' => $p->id,
    //                 'amount' => (float) $p->amount,
    //                 'applied_amount' => (float) $p->applied_amount,
    //                 'credit_amount' => (float) $p->credit_amount,
    //                 'method' => $p->method,
    //                 'paid_at' => $p->paid_at,

    //                 'refunded_invoice' => $refInv,
    //                 'refunded_credit' => $refCr,
    //                 'available_invoice_refund' => $availableInv,
    //                 'available_credit_refund' => $availableCr,

    //                 // لو تحب ترجع refunds نفسها للـ UI:
    //                 'refunds' => $p->refunds->values(),
    //             ];
    //         })->values(),
    //     ];
    // }

    //new copy

}
