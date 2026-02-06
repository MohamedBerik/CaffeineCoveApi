<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($data) {

            $po = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'number' => 'PO-' . now()->format('YmdHis'),
                'status' => 'ordered',
                'total' => 0
            ]);

            $total = 0;

            foreach ($data['items'] as $item) {

                $line = $item['quantity'] * $item['unit_cost'];

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total' => $line,
                ]);

                $total += $line;
            }

            $po->update(['total' => $total]);

            return response()->json($po, 201);
        });
    }
    public function receive(Request $request, $id)
    {
        $po = PurchaseOrder::with('items')->findOrFail($id);

        if ($po->status === 'received') {
            return response()->json([
                'msg' => 'Purchase order already received'
            ], 422);
        }

        return DB::transaction(function () use ($po, $request) {

            foreach ($po->items as $item) {

                $product = Product::lockForUpdate()
                    ->findOrFail($item->product_id);

                $product->increment('stock_quantity', $item->quantity);

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'quantity' => $item->quantity,
                    'reference_type' => PurchaseOrder::class,
                    'reference_id' => $po->id,
                    'created_by' => $request->user()->id ?? null,
                ]);
            }

            $po->update([
                'status' => 'received'
            ]);
            activity('purchase.received', $po);

            return response()->json([
                'msg' => 'Stock received successfully'
            ]);
        });
    }
    public function pay(Request $request, $id)
    {
        $po = PurchaseOrder::with(['payments', 'supplier'])->findOrFail($id);

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string']
        ]);

        $alreadyPaid = $po->payments->sum('amount');
        $remaining   = $po->total - $alreadyPaid;

        if ($request->amount > $remaining) {
            return response()->json([
                'msg' => 'Payment exceeds remaining amount',
                'remaining' => $remaining
            ], 422);
        }

        return DB::transaction(function () use ($po, $request, $remaining) {

            $payment = SupplierPayment::create([
                'supplier_id' => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'amount' => $request->amount,
                'method' => $request->method,
                'paid_at' => now(),
                'paid_by' => $request->user()->id ?? null
            ]);

            $newPaid = $po->payments()->sum('amount') + $request->amount;

            if ($newPaid >= $po->total) {
                $po->update(['status' => 'paid']);
            }

            activity('supplier.paid', $po, [
                'amount' => $request->amount
            ]);

            return response()->json([
                'msg' => 'Supplier payment recorded',
                'payment_id' => $payment->id
            ]);
        });
    }
}
