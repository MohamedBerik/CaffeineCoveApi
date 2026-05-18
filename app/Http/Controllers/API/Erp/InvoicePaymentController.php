<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Erp\InvoicePaymentService;
use Illuminate\Http\Request;

class InvoicePaymentController extends Controller
{
    public function __construct(
        protected InvoicePaymentService $paymentService
    ) {}

    public function index(Request $request)
    {
        $payments = \App\Models\Payment::with(['invoice.customer'])
            ->latest()
            ->paginate(100);

        return response()->json([
            'data' => $payments
        ]);
    }

    public function store(Request $request, $invoiceId)
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $this->authorize('update', $invoice);

        return $this->paymentService->store($request, $invoiceId);
    }
}
