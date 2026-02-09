<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\CustomerLedgerEntry;

class InvoiceController extends Controller
{
    public function indexErp()
    {
        $invoices = Invoice::with([
            'customer',
            'payments.refunds'
        ])
            ->orderBy('issued_at', 'desc')
            ->get()
            ->map(function ($invoice) {

                // مجموع المدفوع
                $totalPaid = $invoice->payments->sum('amount');

                // مجموع المرتجع (من payment_refunds فقط)
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

                    // للواجهة
                    'total_paid' => $totalPaid,
                    'total_refunded' => $totalRefunded,
                    'remaining' => $remaining,

                    // مهم جدًا للـ UI (refund per payment)
                    'payments' => $invoice->payments->map(function ($p) {

                        $refunded = $p->refunds->sum('amount');

                        return [
                            'id' => $p->id,
                            'amount' => $p->amount,
                            'method' => $p->method,
                            'paid_at' => $p->paid_at,

                            // 👇 هذا الذي تستخدمه في الواجهة
                            'refunded_amount' => $refunded,
                        ];
                    }),
                ];
            });

        return response()->json($invoices);
    }
    public function show($id)
    {
        $invoice = Invoice::with([
            'items.product',
            'payments.refunds'
        ])->findOrFail($id);

        return response()->json($invoice);
    }
    public function showFullInvoice($id)
    {
        $invoice = Invoice::with([
            'items.product',        // كل items مربوط بالـ product
            'payments.refunds',
            'journalEntries.lines.account'  // كل القيود المحاسبية والخطوط
        ])->findOrFail($id);

        return response()->json([
            'invoice' => $invoice
        ]);
    }
}
