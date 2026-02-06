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
            'payments',
            'refunds'
        ])
            ->orderBy('issued_at', 'desc')
            ->get()
            ->map(function ($invoice) {

                $totalPaid = $invoice->payments->sum('amount');

                // لو refunds مربوطة مباشرة بالـ invoice
                // استخدم:
                $totalRefunded = $invoice->refunds->sum('amount');

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

                    // 👇 القيم المطلوبة في الواجهة
                    'total_paid' => $totalPaid,
                    'total_refunded' => $totalRefunded,
                    'remaining' => $remaining,

                    // 👇 مهم لزر refund لاحقًا
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
