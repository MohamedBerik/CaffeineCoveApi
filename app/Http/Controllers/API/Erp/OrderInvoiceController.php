<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\CustomerLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderInvoiceController extends Controller
{
    public function store(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {

            // ✅ CompanyScope هيضمن إن الأوردر من نفس الشركة
            // ✅ lockForUpdate يمنع سباق إنشاء فاتورتين لنفس الأوردر
            $order = Order::with(['items', 'invoice'])
                ->lockForUpdate()
                ->findOrFail($id);

            // منع إنشاء أكثر من فاتورة لنفس الأوردر
            if ($order->invoice) {
                return response()->json([
                    'msg' => 'Invoice already exists for this order',
                    'invoice_id' => $order->invoice->id
                ], 422);
            }

            // لازم الأوردر يكون confirmed
            if ($order->status !== 'confirmed') {
                return response()->json([
                    'msg' => 'Only confirmed orders can be invoiced'
                ], 422);
            }

            if ($order->items->isEmpty()) {
                return response()->json([
                    'msg' => 'Order has no items'
                ], 422);
            }

            // حساب الإجمالي من عناصر الأوردر (من السيرفر فقط)
            $total = $order->items->sum(fn($item) => $item->unit_price * $item->quantity);

            // ✅ لا تكتب company_id يدويًا (BelongsToCompanyTrait يملأه)
            $invoice = Invoice::create([
                'number'      => $this->generateInvoiceNumber(),
                'order_id'    => $order->id,
                'customer_id' => $order->customer_id,
                'total'       => $total,
                'status'      => 'unpaid',
                'issued_at'   => now(),
            ]);

            foreach ($order->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total'      => $item->unit_price * $item->quantity,
                ]);
            }

            // ✅ مهم جدًا: نفس منطق confirm (عشان مايبقاش عندك فواتير بدون قيود)
            CustomerLedgerEntry::create([
                'customer_id' => $invoice->customer_id,
                'invoice_id'  => $invoice->id,
                'type'        => 'invoice',
                'debit'       => $invoice->total,
                'credit'      => 0,
                'entry_date'  => now(),
                'description' => 'Invoice ' . $invoice->number,
            ]);

            activity('invoice.created', $invoice);

            return response()->json([
                'msg' => 'Invoice created successfully',
                'invoice_id' => $invoice->id
            ], 201);
        });
    }

    protected function generateInvoiceNumber()
    {
        return 'INV-' . now()->format('YmdHis') . '-' . rand(10, 99);
    }
}
