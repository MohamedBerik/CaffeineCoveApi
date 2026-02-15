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
    public function indexErp(Request $request)
    {
        $companyId = $request->user()->company_id;

        $orders = PurchaseOrder::with([
            'supplier',
            'items.product',
            'payments'
        ])
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($po) {

                $totalPaid = $po->payments->sum('amount');
                $remaining = $po->total - $totalPaid;

                if ($remaining < 0) {
                    $remaining = 0;
                }

                return [
                    'id'          => $po->id,
                    'number'      => $po->number,
                    'status'      => $po->status,
                    'total'       => $po->total,
                    'supplier'    => $po->supplier,

                    'total_paid'  => $totalPaid,
                    'remaining'   => $remaining,
                    'is_received' => ! is_null($po->received_at),
                    'created_at'  => $po->created_at,

                    'payments' => $po->payments->map(function ($p) {
                        return [
                            'id'      => $p->id,
                            'amount' => $p->amount,
                            'method' => $p->method,
                            'paid_at' => $p->paid_at,
                        ];
                    }),
                ];
            });

        return response()->json($orders);
    }

    public function showErp(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $po = PurchaseOrder::with([
            'supplier',
            'items.product',
            'payments'
        ])
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->firstOrFail();

        $totalPaid = $po->payments->sum('amount');
        $remaining = $po->total - $totalPaid;

        if ($remaining < 0) {
            $remaining = 0;
        }

        return response()->json([
            'id'          => $po->id,
            'number'      => $po->number,
            'status'      => $po->status,
            'total'       => $po->total,
            'supplier'    => $po->supplier,
            'created_at'  => $po->created_at,
            'received_at' => $po->received_at,

            'total_paid'  => $totalPaid,
            'remaining'   => $remaining,
            'is_received' => ! is_null($po->received_at),

            'items' => $po->items->map(function ($item) {
                return [
                    'id'        => $item->id,
                    'product'  => $item->product,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'total'    => $item->total,
                ];
            }),

            'payments' => $po->payments->map(function ($p) {
                return [
                    'id'      => $p->id,
                    'amount' => $p->amount,
                    'method' => $p->method,
                    'paid_at' => $p->paid_at,
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $data = $request->validate([
            'supplier_id' => ['required'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($data, $companyId) {

            // تأكد أن المورد تابع لنفس الشركة
            $supplierExists = \App\Models\Supplier::where('company_id', $companyId)
                ->where('id', $data['supplier_id'])
                ->exists();

            if (! $supplierExists) {
                return response()->json([
                    'msg' => 'Invalid supplier'
                ], 422);
            }

            $po = PurchaseOrder::create([
                'company_id'  => $companyId,
                'supplier_id' => $data['supplier_id'],
                'number'      => 'PO-' . now()->format('YmdHis'),
                'status'      => 'ordered',
                'total'       => 0
            ]);

            $total = 0;

            foreach ($data['items'] as $item) {

                // تأكد أن الصنف تابع لنفس الشركة
                $productExists = Product::where('company_id', $companyId)
                    ->where('id', $item['product_id'])
                    ->exists();

                if (! $productExists) {
                    throw new \Exception('Invalid product for this company');
                }

                $line = $item['quantity'] * $item['unit_cost'];

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id'        => $item['product_id'],
                    'quantity'          => $item['quantity'],
                    'unit_cost'         => $item['unit_cost'],
                    'total'             => $line,
                ]);

                $total += $line;
            }

            $po->update(['total' => $total]);

            SupplierLedgerEntry::create([
                'company_id'        => $companyId,
                'supplier_id'       => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'type'              => 'purchase',
                'debit'             => $total,
                'credit'            => 0,
                'entry_date'        => now()->toDateString(),
                'description'       => 'Purchase order #' . $po->number,
            ]);

            return response()->json($po, 201);
        });
    }


    public function receive(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        return DB::transaction(function () use ($request, $id, $companyId) {

            $po = PurchaseOrder::with(['items.product'])
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($po->received_at !== null) {
                return response()->json([
                    'msg' => 'Purchase order already received'
                ], 422);
            }

            if ($po->items->isEmpty()) {
                return response()->json([
                    'msg' => 'Purchase order has no items'
                ], 422);
            }

            /**
             * حماية إضافية:
             * لو حصلت مشكلة قبل كده واتسجلت movements بدون received_at
             */
            $alreadyMoved = StockMovement::where('company_id', $companyId)
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $po->id)
                ->where('type', 'in')
                ->exists();

            if ($alreadyMoved) {
                return response()->json([
                    'msg' => 'Stock already received for this purchase order'
                ], 422);
            }

            foreach ($po->items as $item) {

                $product = Product::where('company_id', $companyId)
                    ->lockForUpdate()
                    ->find($item->product_id);

                if (! $product) {
                    throw new \Exception("Product not found or not in this company");
                }

                $product->increment('stock_quantity', $item->quantity);

                StockMovement::create([
                    'company_id'     => $companyId,
                    'product_id'     => $product->id,
                    'type'           => 'in',
                    'quantity'       => $item->quantity,
                    'reference_type' => PurchaseOrder::class,
                    'reference_id'   => $po->id,
                    'created_by'     => $request->user()->id,
                ]);
            }

            $po->received_at = now();

            /*
         الحالة لا نغيرها هنا
         لأنك بالفعل فصلت بين:
         payment status
         و receiving
         */
            $po->save();

            return response()->json([
                'msg' => 'Purchase order received successfully'
            ]);
        });
    }

    public function pay(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string']
        ]);

        return DB::transaction(function () use ($request, $id, $companyId) {

            $po = PurchaseOrder::with(['payments'])
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($id);

            $alreadyPaid = $po->payments->sum('amount');
            $remaining   = $po->total - $alreadyPaid;

            if ($remaining <= 0) {
                return response()->json([
                    'msg' => 'This purchase order is already fully paid'
                ], 422);
            }

            if ($request->amount > $remaining) {
                return response()->json([
                    'msg' => 'Payment exceeds remaining amount',
                    'remaining' => $remaining
                ], 422);
            }

            /**
             * تأكد أن المورد من نفس الشركة
             */
            $supplier = $po->supplier()
                ->where('company_id', $companyId)
                ->first();

            if (! $supplier) {
                return response()->json([
                    'msg' => 'Supplier does not belong to this company'
                ], 422);
            }

            $payment = SupplierPayment::create([
                'company_id'        => $companyId,
                'supplier_id'       => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'amount'            => $request->amount,
                'method'            => $request->method,
                'paid_at'           => now(),
                'paid_by'           => $request->user()->id
            ]);

            SupplierLedgerEntry::create([
                'company_id'         => $companyId,
                'supplier_id'        => $po->supplier_id,
                'purchase_order_id'  => $po->id,
                'supplier_payment_id' => $payment->id,
                'type'               => 'payment',
                'debit'              => 0,
                'credit'             => $payment->amount,
                'entry_date'         => now()->toDateString(),
                'description'        => 'Payment for PO #' . $po->number,
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

        $user = $request->user();
        $companyId = $user->company_id;

        return DB::transaction(function () use ($request, $id, $companyId, $user) {

            $po = PurchaseOrder::with('items')
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($id);

            $productId = (int) $request->product_id;
            $qty       = (float) $request->quantity;

            $item = $po->items
                ->firstWhere('product_id', $productId);

            if (! $item) {
                return response()->json([
                    'msg' => 'This product does not belong to this purchase order'
                ], 422);
            }

            $totalIn = StockMovement::where('company_id', $companyId)
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $po->id)
                ->where('product_id', $productId)
                ->where('type', 'in')
                ->sum('quantity');

            if ($totalIn <= 0) {
                return response()->json([
                    'msg' => 'This product has not been received yet'
                ], 422);
            }

            $totalOut = StockMovement::where('company_id', $companyId)
                ->where('reference_type', PurchaseOrder::class)
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

            $product = Product::where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($productId);

            if ($product->stock_quantity < $qty) {
                return response()->json([
                    'msg' => 'Insufficient stock to return'
                ], 422);
            }

            $product->decrement('stock_quantity', $qty);

            StockMovement::create([
                'company_id'     => $companyId,
                'product_id'     => $productId,
                'type'           => 'out',
                'quantity'       => $qty,
                'reference_type' => PurchaseOrder::class,
                'reference_id'   => $po->id,
                'created_by'     => $user->id,
            ]);

            $unitCost = $item->unit_cost;

            SupplierLedgerEntry::create([
                'company_id'        => $companyId,
                'supplier_id'       => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'type'              => 'purchase_return',
                'debit'             => 0,
                'credit'            => $qty * $unitCost,
                'entry_date'        => now()->toDateString(),
                'description'       => 'Purchase return for PO #' . $po->number,
            ]);

            /*
        |--------------------------------------------------------------------------
        | تعديل الحالة بشكل ذكي
        |--------------------------------------------------------------------------
        */

            if ($availableToReturn - $qty == 0) {
                $po->status = 'returned';
            } else {
                $po->status = 'has_return';
            }

            $po->save();

            return response()->json([
                'msg' => 'Items returned successfully',
                'returned_quantity' => $qty
            ]);
        });
    }


    public function getReturnableItems(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $po = PurchaseOrder::with(['items.product'])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $items = $po->items->map(function ($item) use ($po, $companyId) {

            $totalIn = StockMovement::where('company_id', $companyId)
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $po->id)
                ->where('product_id', $item->product_id)
                ->where('type', 'in')
                ->sum('quantity');

            $totalOut = StockMovement::where('company_id', $companyId)
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $po->id)
                ->where('product_id', $item->product_id)
                ->where('type', 'out')
                ->sum('quantity');

            $available = $totalIn - $totalOut;

            return [
                'product_id'          => $item->product_id,
                'product_name'        => $item->product?->title_en,
                'ordered_quantity'    => $item->quantity,
                'received_quantity'   => $totalIn,
                'returned_quantity'   => $totalOut,
                'available_to_return' => max(0, $available),
                'unit_price'          => $item->unit_cost,
            ];
        });

        return response()->json([
            'purchase_order_id' => $po->id,
            'items'             => $items->values()
        ]);
    }

    public function returnHistory(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $po = PurchaseOrder::where('company_id', $companyId)
            ->findOrFail($id);

        $rows = StockMovement::with('product')
            ->where('company_id', $companyId)
            ->where('reference_type', PurchaseOrder::class)
            ->where('reference_id', $po->id)
            ->where('type', 'out')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($m) {
                return [
                    'id'         => $m->id,
                    'product_id' => $m->product_id,
                    'product'    => $m->product?->title_en,
                    'quantity'   => $m->quantity,
                    'created_at' => $m->created_at,
                    'created_by' => $m->created_by,
                ];
            });

        return response()->json([
            'purchase_order_id' => $po->id,
            'returns'           => $rows
        ]);
    }
}
