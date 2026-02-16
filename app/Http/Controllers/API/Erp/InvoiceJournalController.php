<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\JournalEntry;
use Illuminate\Http\Request;

class InvoiceJournalController extends Controller
{
    public function index(Request $request, $invoiceId)
    {
        $companyId = $request->user()->company_id;

        // التأكد أن الفاتورة تخص نفس الشركة
        $invoice = Invoice::where('company_id', $companyId)
            ->findOrFail($invoiceId);

        $entries = JournalEntry::with([
            'lines' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            }
        ])
            ->where('company_id', $companyId)
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'invoice_id' => $invoice->id,
            'entries'    => $entries
        ]);
    }
}
