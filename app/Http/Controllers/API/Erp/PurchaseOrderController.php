<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supply;                     // ✅ المستلزمات
use App\Models\StockMovement;
use App\Models\SupplierPayment;
use App\Models\SupplierLedgerEntry;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(PurchaseOrder::class, 'purchaseOrder');
    }

    public function indexErp(Request $request)
    {
        $orders = PurchaseOrder::with([
            'supplier',
            'items.supply',    // ✅ العلاقة supply بدلاً من product
            'payments'
        ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($po) {
                $totalPaid = $po->payments->sum('amount');
                $remaining = max(0, $po->total - $totalPaid);

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
                    'payments'    => $po->payments->map(function ($p) {
                        return [
                            'id'      => $p->id,
                            'amount'  => $p->amount,
                            'method'  => $p->method,
                            'paid_at' => $p->paid_at,
                        ];
                    }),
                ];
            });

        return response()->json($orders);
    }

    public function showErp(Request $request, $id)
    {
        $po = PurchaseOrder::with([
            'supplier',
            'items.supply',   // ✅
            'payments'
        ])->findOrFail($id);

        $totalPaid = $po->payments->sum('amount');
        $remaining = max(0, $po->total - $totalPaid);

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
            'items'       => $po->items->map(function ($item) {
                return [
                    'id'        => $item->id,
                    'supply'    => $item->supply,    // ✅
                    'quantity'  => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'total'     => $item->total,
                ];
            }),
            'payments'    => $po->payments->map(function ($p) {
                return [
                    'id'      => $p->id,
                    'amount'  => $p->amount,
                    'method'  => $p->method,
                    'paid_at' => $p->paid_at,
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = Tenant::id();

        $data = $request->validate([
            'supplier_id'           => ['required'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.supply_id'     => ['required'],       // ✅ supply_id
            'items.*.quantity'      => ['required', 'integer', 'min:1'],
            'items.*.unit_cost'     => ['required', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($data, $companyId) {
            $supplierExists = \App\Models\Supplier::query()
                ->where('id', $data['supplier_id'])
                ->exists();
            if (! $supplierExists) {
                return response()->json(['msg' => 'Invalid supplier'], 422);
            }

            $po = PurchaseOrder::create([
                'company_id'  => $companyId,
                'supplier_id' => $data['supplier_id'],
                'number'      => 'PO-' . now()->format('YmdHis'),
                'status'      => 'ordered',
                'total'       => 0,
            ]);

            $total = 0;
            foreach ($data['items'] as $item) {
                // ✅ تحقق من وجود المستلزم
                $supplyExists = Supply::query()->where('id', $item['supply_id'])->exists();
                if (! $supplyExists) {
                    throw new \Exception('Invalid supply for this company');
                }

                $line = $item['quantity'] * $item['unit_cost'];

                PurchaseOrderItem::create([
                    'company_id'        => $companyId,
                    'purchase_order_id' => $po->id,
                    'supply_id'         => $item['supply_id'],   // ✅
                    'quantity'          => $item['quantity'],
                    'unit_cost'         => $item['unit_cost'],
                    'total'             => $line,
                ]);

                $total += $line;
            }

            $po->update(['total' => $total]);

            // قيد يومية المورد (مدين)
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
        $companyId = Tenant::id();

        return DB::transaction(function () use ($request, $id, $companyId) {
            $po = PurchaseOrder::with(['items.supply'])   // ✅
                ->lockForUpdate()
                ->findOrFail($id);

            if ($po->received_at !== null) {
                return response()->json(['msg' => 'Purchase order already received'], 422);
            }

            if ($po->items->isEmpty()) {
                return response()->json(['msg' => 'Purchase order has no items'], 422);
            }

            // ✅ التحقق من عدم وجود حركة استلام سابقة
            $alreadyMoved = StockMovement::query()
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $po->id)
                ->where('type', 'in')
                ->exists();

            if ($alreadyMoved) {
                return response()->json(['msg' => 'Stock already received for this purchase order'], 422);
            }

            foreach ($po->items as $item) {
                // ✅ تعامل مع Supply بدلاً من Product
                $supply = Supply::query()->lockForUpdate()->find($item->supply_id);
                if (! $supply) {
                    throw new \Exception("Supply not found or not in this company");
                }

                $supply->increment('stock_quantity', $item->quantity);

                StockMovement::create([
                    'company_id'     => $companyId,
                    'supply_id'      => $supply->id,    // ✅ تم التغيير
                    'type'           => 'in',
                    'quantity'       => $item->quantity,
                    'reference_type' => PurchaseOrder::class,
                    'reference_id'   => $po->id,
                    'created_by'     => $request->user()->id,
                ]);
            }

            $po->received_at = now();
            $po->save();

            return response()->json(['msg' => 'Purchase order received successfully']);
        });
    }

    public function pay(Request $request, $id)
    {
        $companyId = Tenant::id();

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($request, $id, $companyId) {
            $po = PurchaseOrder::with(['payments'])->lockForUpdate()->findOrFail($id);

            $alreadyPaid = $po->payments->sum('amount');
            $remaining   = $po->total - $alreadyPaid;

            if ($remaining <= 0) {
                return response()->json(['msg' => 'This purchase order is already fully paid'], 422);
            }

            if ($request->amount > $remaining) {
                return response()->json([
                    'msg'       => 'Payment exceeds remaining amount',
                    'remaining' => $remaining,
                ], 422);
            }

            $supplier = $po->supplier()->first();
            if (! $supplier) {
                return response()->json(['msg' => 'Supplier does not belong to this company'], 422);
            }

            $payment = SupplierPayment::create([
                'company_id'        => $companyId,
                'supplier_id'       => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'amount'            => $request->amount,
                'method'            => $request->method,
                'paid_at'           => now(),
                'paid_by'           => $request->user()->id,
            ]);

            SupplierLedgerEntry::create([
                'company_id'          => $companyId,
                'supplier_id'         => $po->supplier_id,
                'purchase_order_id'   => $po->id,
                'supplier_payment_id' => $payment->id,
                'type'                => 'payment',
                'debit'               => 0,
                'credit'              => $payment->amount,
                'entry_date'          => now()->toDateString(),
                'description'         => 'Payment for PO #' . $po->number,
            ]);

            $newPaid = $alreadyPaid + $request->amount;
            $po->status = $newPaid < $po->total ? 'partially_paid' : 'paid';
            $po->save();

            return response()->json([
                'msg'        => 'Supplier payment recorded',
                'payment_id' => $payment->id,
            ]);
        });
    }

    public function returnItems(Request $request, $id)
    {
        $request->validate([
            'supply_id' => ['required', 'exists:supplies,id'],   // ✅ تغيير إلى supply_id
            'quantity'  => ['required', 'numeric', 'min:0.01'],
        ]);

        $companyId = Tenant::id();
        $user      = $request->user();

        return DB::transaction(function () use ($request, $id, $companyId, $user) {
            $po = PurchaseOrder::with('items')->lockForUpdate()->findOrFail($id);

            $supplyId = (int) $request->supply_id;   // ✅
            $qty      = (float) $request->quantity;

            // ✅ البحث عن العنصر المرتبط بالمستلزم
            $item = $po->items->firstWhere('supply_id', $supplyId);
            if (! $item) {
                return response()->json(['msg' => 'This supply does not belong to this purchase order'], 422);
            }

            // ✅ الكميات المستلمة والمرتجعة للمستلزم (نستخدم حقل product_id مؤقتاً لـ supply)
            $totalIn = StockMovement::query()
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $po->id)
                ->where('supply_id', $supplyId)        // ✅ تم التغيير
                ->where('type', 'in')
                ->sum('quantity');

            if ($totalIn <= 0) {
                return response()->json(['msg' => 'This supply has not been received yet'], 422);
            }

            $totalOut = StockMovement::query()
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $po->id)
                ->where('supply_id', $supplyId)        // ✅ تم التغيير
                ->where('type', 'out')
                ->sum('quantity');

            $availableToReturn = $totalIn - $totalOut;

            if ($availableToReturn <= 0) {
                return response()->json(['msg' => 'No received quantity available to return for this supply'], 422);
            }

            if ($qty > $availableToReturn) {
                return response()->json([
                    'msg'       => 'Return quantity exceeds received quantity',
                    'available' => $availableToReturn,
                ], 422);
            }

            // ✅ استخدام Supply بدلاً من Product
            $supply = Supply::query()->lockForUpdate()->findOrFail($supplyId);

            if ($supply->stock_quantity < $qty) {
                return response()->json(['msg' => 'Insufficient stock to return'], 422);
            }

            $supply->decrement('stock_quantity', $qty);

            // سجل حركة المخزون (نوع out)
            StockMovement::create([
                'company_id'     => $companyId,
                'product_id'     => $supplyId,   // ✅
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

            // تحديث حالة الأمر
            $po->status = ($availableToReturn - $qty == 0) ? 'returned' : 'has_return';
            $po->save();

            return response()->json([
                'msg'               => 'Items returned successfully',
                'returned_quantity' => $qty,
            ]);
        });
    }

    public function getReturnableItems(Request $request, $id)
    {
        $po = PurchaseOrder::with(['items.supply'])->findOrFail($id);  // ✅

        $items = $po->items->map(function ($item) use ($po) {
            $totalIn = StockMovement::query()
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $po->id)
                ->where('supply_id', $item->supply_id)   // ✅ تم التغيير
                ->where('type', 'in')
                ->sum('quantity');

            $totalOut = StockMovement::query()
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $po->id)
                ->where('supply_id', $item->supply_id)   // ✅ تم التغيير
                ->where('type', 'out')
                ->sum('quantity');

            $available = $totalIn - $totalOut;

            return [
                'product_id'          => $item->supply_id,   // ملاحظة: ما زلنا نستخدم product_id في الـ response للتوافق مع الواجهة القديمة
                'product_name'        => $item->supply?->name, // ✅
                'ordered_quantity'    => $item->quantity,
                'received_quantity'   => $totalIn,
                'returned_quantity'   => $totalOut,
                'available_to_return' => max(0, $available),
                'unit_price'          => $item->unit_cost,
            ];
        });

        return response()->json([
            'purchase_order_id' => $po->id,
            'items'             => $items->values(),
        ]);
    }

    public function returnHistory(Request $request, $id)
    {
        $po = PurchaseOrder::query()->findOrFail($id);

        $rows = StockMovement::with('supply')    // ✅ علاقة supply في StockMovement
            ->where('reference_type', PurchaseOrder::class)
            ->where('reference_id', $po->id)
            ->where('type', 'out')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($m) {
                return [
                    'id'         => $m->id,
                    'supply_id'  => $m->supply_id,     // ✅ تم التغيير
                    'supply'     => $m->supply?->name, // ✅ تم التغيير
                    'quantity'   => $m->quantity,
                    'created_at' => $m->created_at,
                    'created_by' => $m->created_by,
                ];
            });

        return response()->json([
            'purchase_order_id' => $po->id,
            'returns'           => $rows,
        ]);
    }
}
