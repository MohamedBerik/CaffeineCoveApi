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
            'customer' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            },
            'payments' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            },
            'payments.refunds' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            },
        ])

            ->where('company_id', $companyId)
            ->orderBy('issued_at', 'desc')
            ->get()
            ->map(function ($invoice) {

                $totalPaid = $invoice->payments->sum('amount');

                $totalRefunded = $invoice->payments->sum(function ($p) {
                    return $p->refunds->sum('amount');
                });

                $remaining = $invoice->total - ($totalPaid - $totalRefunded);

                if ($remaining < 0) {
                    $remaining = 0;
                }

                return [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'issued_at' => $invoice->issued_at,
                    'total' => $invoice->total,
                    'status' => $invoice->status,

                    'customer' => $invoice->customer,

                    'total_paid' => $totalPaid - $totalRefunded,
                    'total_refunded' => $totalRefunded,
                    'remaining' => $remaining,

                    'payments' => $invoice->payments->map(function ($p) {

                        $refunded = $p->refunds->sum('amount');

                        return [
                            'id' => $p->id,
                            'amount' => $p->amount,
                            'method' => $p->method,
                            'paid_at' => $p->paid_at,
                            'refunded_amount' => $refunded,
                        ];
                    }),
                ];
            });

        return response()->json($invoices);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $invoice = Invoice::with([
            'items' => fn($q) => $q->where('company_id', $companyId),
            'items.product' => fn($q) => $q->where('company_id', $companyId),

            'payments' => fn($q) => $q->where('company_id', $companyId),
            'payments.refunds' => fn($q) => $q->where('company_id', $companyId),
        ])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json($invoice);
    }

    public function showFullInvoice(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $invoice = Invoice::with([
            'items' => fn($q) => $q->where('company_id', $companyId),
            'items.product' => fn($q) => $q->where('company_id', $companyId),

            'payments' => fn($q) => $q->where('company_id', $companyId),
            'payments.refunds' => fn($q) => $q->where('company_id', $companyId),

            'journalEntries' => fn($q) => $q->where('company_id', $companyId),
            'journalEntries.lines',
            'journalEntries.lines.account' => fn($q) => $q->where('company_id', $companyId),
        ])

            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json([
            'invoice' => $invoice
        ]);
    }
}
