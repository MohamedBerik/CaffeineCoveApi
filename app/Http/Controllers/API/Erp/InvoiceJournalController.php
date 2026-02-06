<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\JournalEntry;

class InvoiceJournalController extends Controller
{
    public function index(Invoice $invoice)
    {
        $entries = JournalEntry::with('lines')
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'invoice_id' => $invoice->id,
            'entries' => $entries
        ]);
    }
}
