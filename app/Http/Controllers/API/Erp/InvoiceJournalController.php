<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceJournalController extends Controller
{
    public function index(Request $request, $invoiceId)
    {
        $companyId = $request->user()->company_id;

        // ✅ تأكد أن الفاتورة تخص نفس الشركة
        $invoice = Invoice::where('company_id', $companyId)->findOrFail($invoiceId);

        // ✅ اعتمد على علاقة journalEntries (morphMany) بدل query يدوي
        $entries = $invoice->journalEntries()
            ->with([
                'lines.account' => function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                }
            ])
            ->orderBy('id')
            ->get();

        return response()->json([
            'invoice_id' => $invoice->id,
            'entries'    => $entries
        ]);
    }
}
