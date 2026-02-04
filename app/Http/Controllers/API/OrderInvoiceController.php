<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderInvoiceController extends Controller
{
    public function store(Order $order)
    {
        // منع إنشاء أكثر من فاتورة لنفس الأوردر
        if ($order->invoice) {
            return response()->json([
                'msg' => 'Invoice already exists for this order',
                'invoice_id' => $order->invoice->id
            ], 422);
        }

        // التأكد أن الأوردر مؤكد
        if ($order->status !== 'confirmed') {
            return response()->json([
                'msg' => 'Only confirmed orders can be invoiced'
            ], 422);
        }

        $order->load('items');

        if ($order->items->isEmpty()) {
            return response()->json([
                'msg' => 'Order has no items'
            ], 422);
        }

        return DB::transaction(function () use ($order) {

            // حساب إجمالي الفاتورة
            $total = 0;
            foreach ($order->items as $item) {
                $total += $item->unit_price * $item->quantity;
            }

            // إنشاء الفاتورة
            $invoice = Invoice::create([
                'number'      => $this->generateInvoiceNumber(),
                'order_id'    => $order->id,
                'customer_id' => $order->customer_id ?? null,
                'total'       => $total,
                'status'      => 'unpaid',
                'issued_at'   => now()
            ]);

            // إنشاء تفاصيل الفاتورة (Invoice Items)
            foreach ($order->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item->product_id ?? null,
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
