<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Tenant;
use Illuminate\Http\Request;

class InvoiceJournalController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Invoice::class, 'invoice');
    }

    public function index(Request $request, $invoiceId)
    {
        // ✅ تأكد أن الفاتورة تخص نفس الشركة (الـ Scope هيتأكد)
        $invoice = Invoice::findOrFail($invoiceId);

        // ✅ اعتمد على علاقة journalEntries (morphMany)
        $entries = $invoice->journalEntries()
            ->with([
                'lines.account'
            ])
            ->orderBy('id')
            ->get();

        return response()->json([
            'invoice_id' => $invoice->id,
            'entries'    => $entries
        ]);
    }
}
