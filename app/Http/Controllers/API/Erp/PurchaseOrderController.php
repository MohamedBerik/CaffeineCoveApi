<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\SupplierPayment;
use App\Models\SupplierLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function indexErp()
    {
        $orders = PurchaseOrder::with([
            'supplier',
            'items.product',
            'payments'
        ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($po) {

                $totalPaid = $po->payments->sum('amount');
                $remaining = $po->total - $totalPaid;

                if ($remaining < 0) {
                    $remaining = 0;
                }

                return [
                    'id' => $po->id,
                    'number' => $po->number,
                    'status' => $po->status,
                    'total' => $po->total,
                    'supplier' => $po->supplier,
                    // للواجهة
                    'total_paid' => $totalPaid,
                    'remaining'  => $remaining,
                    'is_received' => ! is_null($po->received_at),
                    'created_at' => $po->created_at,
                    'payments' => $po->payments->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'amount' => $p->amount,
                            'method' => $p->method,
                            'paid_at' => $p->paid_at,
                        ];
                    }),
                ];
            });

        return response()->json($orders);
    }
    public function showErp($id)
    {
        $po = PurchaseOrder::with([
            'supplier',
            'items.product',
            'payments'
        ])->findOrFail($id);

        $totalPaid = $po->payments->sum('amount');
        $remaining = $po->total - $totalPaid;

        if ($remaining < 0) {
            $remaining = 0;
        }

        return response()->json([
            'id' => $po->id,
            'number' => $po->number,
            'status' => $po->status,
            'total' => $po->total,
            'supplier' => $po->supplier,
            'created_at' => $po->created_at,
            'received_at' => $po->received_at,
            // للواجهة
            'total_paid' => $totalPaid,
            'remaining'  => $remaining,
            'is_received' => ! is_null($po->received_at),
            'items' => $po->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product' => $item->product,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'total' => $item->total,
                ];
            }),

            'payments' => $po->payments->map(function ($p) {
                return [
                    'id' => $p->id,
                    'amount' => $p->amount,
                    'method' => $p->method,
                    'paid_at' => $p->paid_at,
                ];
            }),
        ]);
    }
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
            SupplierLedgerEntry::create([
                'supplier_id' => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'type' => 'purchase',
                'debit' => $total,
                'credit' => 0,
                'entry_date' => now()->toDateString(),
                'description' => 'Purchase order #' . $po->number,
            ]);


            return response()->json($po, 201);
        });
    }
    public function receive(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {

            $po = PurchaseOrder::with('items.product')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($po->received_at !== null) {
                return response()->json([
                    'msg' => 'Purchase order already received'
                ], 422);
            }

            $alreadyMoved = StockMovement::where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $po->id)
                ->exists();

            if ($alreadyMoved) {
                return response()->json([
                    'msg' => 'Stock already received for this purchase order'
                ], 422);
            }

            if ($po->items->isEmpty()) {
                return response()->json([
                    'msg' => 'Purchase order has no items'
                ], 422);
            }

            foreach ($po->items as $item) {

                $product = $item->product;

                if (!$product) {
                    throw new \Exception("Product not found for item {$item->id}");
                }

                $product->increment('stock_quantity', $item->quantity);

                StockMovement::create([
                    'product_id'     => $product->id,
                    'type'           => 'in',
                    'quantity'       => $item->quantity,
                    'reference_type' => PurchaseOrder::class,
                    'reference_id'   => $po->id,
                    'created_by'     => $request->user()?->id,
                ]);
            }

            $po->received_at = now();

            $po->update([
                'received_at' => now(),
                // 'status'      => 'received',
            ]);

            $po->save();

            return response()->json([
                'msg' => 'Purchase order received successfully'
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

        return DB::transaction(function () use ($po, $request, $alreadyPaid) {

            $payment = SupplierPayment::create([
                'supplier_id' => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'amount' => $request->amount,
                'method' => $request->method,
                'paid_at' => now(),
                'paid_by' => $request->user()->id ?? null
            ]);
            SupplierLedgerEntry::create([
                'supplier_id'         => $po->supplier_id,
                'purchase_order_id'   => $po->id,
                'supplier_payment_id' => $payment->id,
                'type'                => 'payment',
                'debit'               => 0,
                'credit'              => $payment->amount,
                'entry_date'          => now()->toDateString(),
                'description' => 'Payment for PO #' . $po->number,
            ]);

            $newPaid = $alreadyPaid + $request->amount;

            if ($newPaid < $po->total) {
                $po->status = 'partially_paid';
            } else {
                $po->status = 'paid';
            }


            $po->save();

            activity('supplier.paid', $po, [
                'amount' => $request->amount
            ]);

            return response()->json([
                'msg' => 'Supplier payment recorded',
                'payment_id' => $payment->id
            ]);
        });
    }
    public function returnItems(Request $request, $id)
    {
        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity'   => ['required', 'numeric', 'min:0.01'],
        ]);

        $po = PurchaseOrder::findOrFail($id);

        return DB::transaction(function () use ($request, $po) {

            $productId = $request->product_id;
            $qty       = $request->quantity;

            $hasAnyReceive = StockMovement::where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $po->id)
                ->where('type', 'in')
                ->exists();

            if (! $hasAnyReceive) {
                return response()->json([
                    'msg' => 'This purchase order has not been received yet'
                ], 422);
            }

            // مجموع الداخل
            $totalIn = StockMovement::where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $po->id)
                ->where('product_id', $productId)
                ->where('type', 'in')
                ->sum('quantity');

            // مجموع المرتجع
            $totalOut = StockMovement::where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $po->id)
                ->where('product_id', $productId)
                ->where('type', 'out')
                ->sum('quantity');

            $availableToReturn = $totalIn - $totalOut;

            if ($availableToReturn <= 0) {
                return response()->json([
                    'msg' => 'No received quantity available to return for this product'
                ], 422);
            }

            if ($qty > $availableToReturn) {
                return response()->json([
                    'msg' => 'Return quantity exceeds received quantity',
                    'available' => $availableToReturn
                ], 422);
            }

            $product = Product::lockForUpdate()->findOrFail($productId);

            if ($product->stock_quantity < $qty) {
                return response()->json([
                    'msg' => 'Insufficient stock to return'
                ], 422);
            }

            // إنقاص المخزون
            $product->decrement('stock_quantity', $qty);

            // تسجيل حركة مرتجع
            StockMovement::create([
                'product_id'     => $productId,
                'type'           => 'out',
                'quantity'       => $qty,
                'reference_type' => PurchaseOrder::class,
                'reference_id'   => $po->id,
                'created_by'     => $request->user()?->id,
            ]);

            return response()->json([
                'msg' => 'Items returned successfully'
            ]);
        });
    }
}
