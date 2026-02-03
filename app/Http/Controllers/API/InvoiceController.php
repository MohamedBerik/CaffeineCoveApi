<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;

class InvoiceController extends Controller
{
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
