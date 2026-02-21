<?php

namespace App\Http\Controllers\API\Erp;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\CustomerLedgerEntry;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function indexErp(Request $request)
    {
        // ✅ لا نحتاج company_id هنا لأن CompanyScope سيعزل تلقائيًا
        $orders = Order::with(['customer', 'items.product', 'invoice'])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function showErp(Request $request, $id)
    {
        // ✅ CompanyScope سيمنع مشاهدة أوردر من شركة أخرى
        $order = Order::with([
            'customer',
            'items.product',
            'invoice.payments.refunds', // ✅ تأكد refunds مربوطة على payments
        ])->findOrFail($id);

        return response()->json($order);
    }

    public function storeErp(Request $request)
    {
        $companyId = $request->user()->company_id;

        // ✅ validation مهم جدًا هنا لأنه بيمنع اختيار customer/product من شركة أخرى
        $data = $request->validate([
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where('company_id', $companyId),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where('company_id', $companyId),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($data, $request) {

            // ✅ لا تكتب company_id يدويًا طالما BelongsToCompanyTrait يملأه تلقائيًا
            $order = Order::create([
                'customer_id'    => $data['customer_id'],
                'status'         => 'pending',
                'total'          => 0,
                'created_by'     => $request->user()->id,
                'title_en'       => $request->input('title_en'),
                'title_ar'       => $request->input('title_ar'),
                'description_en' => $request->input('description_en'),
                'description_ar' => $request->input('description_ar'),
            ]);

            $total = 0;

            foreach ($data['items'] as $row) {

                // ✅ CompanyScope سيحصر المنتج داخل شركة المستخدم تلقائيًا
                $product = Product::lockForUpdate()->findOrFail($row['product_id']);

                if ($product->quantity < $row['quantity']) {
                    abort(422, "Insufficient stock for product {$product->id}");
                }

                $lineTotal = $product->unit_price * $row['quantity'];

                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'quantity'   => $row['quantity'],
                    'unit_price' => $product->unit_price,
                    'total'      => $lineTotal,
                ]);

                $product->decrement('quantity', $row['quantity']);

                StockMovement::create([
                    'product_id'     => $product->id,
                    'type'           => 'out',
                    'quantity'       => $row['quantity'],
                    'reference_type' => Order::class,
                    'reference_id'   => $order->id,
                    'created_by'     => $request->user()->id,
                ]);

                $total += $lineTotal;
            }

            $order->update(['total' => $total]);

            return response()->json([
                'msg'  => 'Order created (ERP)',
                'data' => $order->load('items.product'),
            ], 201);
        });
    }

    public function confirm(Request $request, $id)
    {
        // ✅ lockForUpdate لمنع سباق إنشاء فاتورة مرتين في نفس اللحظة
        $order = Order::with(['items', 'invoice'])
            ->lockForUpdate()
            ->findOrFail($id);

        if ($order->invoice) {
            return response()->json([
                'msg' => 'Invoice already exists for this order',
            ], 422);
        }

        if ($order->status === 'cancelled') {
            return response()->json([
                'msg' => 'Cannot confirm a cancelled order',
            ], 422);
        }

        if ($order->status === 'confirmed') {
            return response()->json([
                'msg' => 'Order already confirmed',
            ], 422);
        }

        if ($order->items->isEmpty()) {
            return response()->json([
                'msg' => 'Order has no items',
            ], 422);
        }

        return DB::transaction(function () use ($order, $request) {

            $total = $order->items->sum(fn($item) => $item->quantity * $item->unit_price);

            $order->update(['status' => 'confirmed']);

            // ✅ لا تكتب company_id يدويًا
            $invoice = Invoice::create([
                'number'      => 'INV-' . now()->format('YmdHis') . '-' . $order->id,
                'order_id'    => $order->id,
                'customer_id' => $order->customer_id,
                'total'       => $total,
                'status'      => 'unpaid',
                'issued_at'   => now(),
            ]);

            CustomerLedgerEntry::create([
                'customer_id' => $invoice->customer_id,
                'invoice_id'  => $invoice->id,
                'type'        => 'invoice',
                'debit'       => $invoice->total,
                'credit'      => 0,
                'entry_date'  => now(),
                'description' => 'Invoice ' . $invoice->number,
            ]);

            foreach ($order->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total'      => $item->quantity * $item->unit_price,
                ]);
            }

            activity('order.confirmed', $order);

            return response()->json([
                'msg' => 'Order confirmed and invoice created',
                'invoice_id' => $invoice->id,
            ]);
        });
    }

    public function cancel(Request $request, $id)
    {
        $order = Order::with('items')->findOrFail($id);

        if ($order->status === 'cancelled') {
            return response()->json([
                'msg' => 'Order already cancelled',
            ], 422);
        }

        return DB::transaction(function () use ($order, $request) {

            foreach ($order->items as $item) {

                $product = Product::lockForUpdate()->findOrFail($item->product_id);

                $product->increment('quantity', $item->quantity);

                StockMovement::create([
                    'product_id'     => $product->id,
                    'type'           => 'in',
                    'quantity'       => $item->quantity,
                    'reference_type' => Order::class,
                    'reference_id'   => $order->id,
                    'created_by'     => $request->user()->id,
                ]);
            }

            $order->update(['status' => 'cancelled']);

            activity('order.cancelled', $order);

            return response()->json([
                'msg' => 'Order cancelled and stock restored',
            ]);
        });
    }
}
