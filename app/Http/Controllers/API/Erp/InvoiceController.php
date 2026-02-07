<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    public function indexErp()
    {
        $invoices = Invoice::with([
            'customer',
            'payments.refunds',
            'refunds'
        ])
            ->orderBy('issued_at', 'desc')
            ->get()
            ->map(function ($invoice) {

                $totalPaid = $invoice->payments->sum('amount');

                $totalRefunded = $invoice->refunds->sum('payment_refunds.amount');

                $remaining = $invoice->total - ($totalPaid - $totalRefunded);

                if ($remaining < 0) {
                    $remaining = 0;
                }

                // 👇 أضف refunded_amount لكل payment
                $invoice->payments->each(function ($p) {
                    $p->refunded_amount = $p->refunds->sum('payment_refunds.amount');
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
                    'remaining' => $remaining,

                    'payments' => $invoice->payments,
                ];
            });

        return response()->json($invoices);
    }

    public function show($id)
    {
        $invoice = Invoice::with([
            'items.product',
            'payments',
            'refunds'
        ])->findOrFail($id);

        return response()->json($invoice);
    }
    public function showFullInvoice($id)
    {
        $invoice = Invoice::with([
            'items.product',        // كل items مربوط بالـ product
            'payments',             // كل الـ payments
            'refunds',              // كل الـ refunds
            'journalEntries.lines'  // كل القيود المحاسبية والخطوط
        ])->findOrFail($id);

        return response()->json([
            'invoice' => $invoice
        ]);
    }
}
