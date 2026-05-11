<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Erp\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {
        $this->authorizeResource(Invoice::class, 'invoice', [
            'except' => ['indexErp', 'show', 'showFullInvoice']
        ]);
    }

    public function indexErp(Request $request)
    {
        $data = $this->invoiceService->indexErp($request);
        return response()->json($data);
    }

    public function show(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $this->authorize('view', $invoice);
        $data = $this->invoiceService->show($request, $id);
        return response()->json($data);
    }

    public function showFullInvoice(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $this->authorize('view', $invoice);
        $data = $this->invoiceService->showFullInvoice($request, $id);
        return response()->json($data);
    }
}
