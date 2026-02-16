<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderInvoiceController extends Controller
{
    public function store(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $order = Order::with('items')
            ->where('company_id', $companyId)
            ->findOrFail($id);

        // منع إنشاء أكثر من فاتورة لنفس الأوردر
        if ($order->invoice) {
            return response()->json([
                'msg' => 'Invoice already exists for this order',
                'invoice_id' => $order->invoice->id
            ], 422);
        }

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

        return DB::transaction(function () use ($order, $companyId) {

            $total = 0;

            foreach ($order->items as $item) {
                $total += $item->unit_price * $item->quantity;
            }

            $invoice = Invoice::create([
                'company_id' => $companyId,   // ✅ مهم
                'number'      => $this->generateInvoiceNumber(),
                'order_id'    => $order->id,
                'customer_id' => $order->customer_id,
                'total'       => $total,
                'status'      => 'unpaid',
                'issued_at'   => now()
            ]);

            foreach ($order->items as $item) {

                InvoiceItem::create([
                    'company_id' => $companyId,   // ✅ لو العمود موجود
                    'invoice_id' => $invoice->id,
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total'      => $item->unit_price * $item->quantity,
                ]);
            }

            return response()->json([
                'msg' => 'Invoice created successfully',
                'invoice_id' => $invoice->id
            ], 201);
        });
    }


    /**
     * توليد رقم فاتورة تلقائي
     */
    protected function generateInvoiceNumber()
    {
        return 'INV-' . now()->format('YmdHis') . '-' . rand(10, 99);
    }
}
