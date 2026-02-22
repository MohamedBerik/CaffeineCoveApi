<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function indexErp(Request $request)
    {
        $companyId = $request->user()->company_id;

        $invoices = Invoice::with(['customer'])
            ->where('company_id', $companyId)
            ->orderByDesc('issued_at')
            ->get()
            ->map(function ($invoice) use ($companyId) {

                $totalPaid = DB::table('payments')
                    ->where('company_id', $companyId)
                    ->where('invoice_id', $invoice->id)
                    ->sum('amount');

                $totalRefunded = DB::table('payment_refunds')
                    ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
                    ->where('payments.company_id', $companyId)
                    ->where('payment_refunds.company_id', $companyId)
                    ->where('payments.invoice_id', $invoice->id)
                    ->sum('payment_refunds.amount'); // ✅ حل مشكلة ambiguous amount

                $netPaid = $totalPaid - $totalRefunded;
                $remaining = max(0, $invoice->total - $netPaid);

                $payments = DB::table('payments')
                    ->where('company_id', $companyId)
                    ->where('invoice_id', $invoice->id)
                    ->orderBy('id')
                    ->get()
                    ->map(function ($p) use ($companyId) {
                        $refunded = DB::table('payment_refunds')
                            ->where('company_id', $companyId)
                            ->where('payment_id', $p->id)
                            ->sum('amount');

                        return [
                            'id' => $p->id,
                            'amount' => $p->amount,
                            'method' => $p->method,
                            'paid_at' => $p->paid_at,
                            'refunded_amount' => $refunded,
                        ];
                    });

                return [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'issued_at' => $invoice->issued_at,
                    'total' => $invoice->total,
                    'status' => $invoice->status,
                    'customer' => $invoice->customer,

                    'total_paid' => $totalPaid,
                    'total_refunded' => $totalRefunded,
                    'net_paid' => $netPaid,
                    'remaining' => $remaining,

                    'payments' => $payments,
                ];
            });

        return response()->json($invoices);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $invoice = Invoice::with(['customer', 'items.product', 'payments.refunds'])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        // نفس حسابات DB لضمان الدقة
        $totalPaid = DB::table('payments')
            ->where('company_id', $companyId)
            ->where('invoice_id', $invoice->id)
            ->sum('amount');

        $totalRefunded = DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->where('payments.company_id', $companyId)
            ->where('payment_refunds.company_id', $companyId)
            ->where('payments.invoice_id', $invoice->id)
            ->sum('payment_refunds.amount');

        $netPaid = $totalPaid - $totalRefunded;

        return response()->json([
            'invoice' => $invoice,
            'total_paid' => $totalPaid,
            'total_refunded' => $totalRefunded,
            'net_paid' => $netPaid,
            'remaining' => max(0, $invoice->total - $netPaid),
        ]);
    }

    public function showFullInvoice(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $invoice = Invoice::with([
            'customer',
            'items.product',
            'payments.refunds',
            'journalEntries.lines.account',
        ])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $payload = $this->transformInvoice($invoice);
        $payload['items'] = $invoice->items->values();
        $payload['journal_entries'] = $invoice->journalEntries->values();

        return response()->json([
            'invoice' => $payload
        ]);
    }

    /**
     * ✅ توحيد شكل الفاتورة في كل endpoints
     */
    private function transformInvoice(Invoice $invoice): array
    {
        $totalPaid = $invoice->payments->sum('amount');

        $totalRefunded = $invoice->payments->sum(function ($p) {
            return $p->refunds->sum('amount');
        });

        $netPaid = $totalPaid - $totalRefunded;
        $remaining = max(0, $invoice->total - $netPaid);

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'issued_at' => $invoice->issued_at,
            'total' => $invoice->total,
            'status' => $invoice->status,

            'customer' => $invoice->customer,

            // ✅ computed for UI
            'total_paid' => $netPaid,
            'total_refunded' => $totalRefunded,
            'net_paid' => $netPaid,
            'remaining' => $remaining,

            // ✅ payments + refunded_amount (UI needs it)
            'payments' => $invoice->payments->map(function ($p) {
                $refunded = $p->refunds->sum('amount');

                return [
                    'id' => $p->id,
                    'amount' => $p->amount,
                    'method' => $p->method,
                    'paid_at' => $p->paid_at,
                    'refunded_amount' => $refunded,
                ];
            })->values(),
        ];
    }
}
