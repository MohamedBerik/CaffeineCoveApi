<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function indexErp(Request $request)
    {
        $companyId = $request->user()->company_id;

        $invoices = Invoice::with([
            'customer',
            'payments.refunds',
        ])
            ->where('company_id', $companyId)
            ->orderByDesc('issued_at')
            ->get()
            ->map(fn($invoice) => $this->transformInvoice($invoice));

        return response()->json($invoices->values());
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $invoice = Invoice::with([
            'customer',
            'items.product',
            'payments.refunds',
        ])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        // ✅ نوحد نفس شكل indexErp + نضيف items
        $payload = $this->transformInvoice($invoice);
        $payload['items'] = $invoice->items->values();

        return response()->json($payload);
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
