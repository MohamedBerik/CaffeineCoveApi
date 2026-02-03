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
}
