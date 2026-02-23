<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerLedgerEntry;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function indexErp(Request $request)
    {
        $companyId = $request->user()->company_id;

        $orders = Order::with(['customer', 'items.product', 'invoice'])
            ->where('company_id', $companyId)
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function showErp(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $order = Order::with([
            'customer',
            'items.product',
            'invoice.payments.refunds',
        ])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json($order);
    }

    public function storeErp(Request $request)
    {
        $companyId = $request->user()->company_id;

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

        return DB::transaction(function () use ($data, $request, $companyId) {

            $order = Order::create([
                'company_id'    => $companyId,
                'customer_id'   => $data['customer_id'],
                'status'        => 'pending',
                'total'         => 0,
                'created_by'    => $request->user()->id ?? null,

                'title_en'       => $request->input('title_en'),
                'title_ar'       => $request->input('title_ar'),
                'description_en' => $request->input('description_en'),
                'description_ar' => $request->input('description_ar'),
            ]);

            $total = 0;

            foreach ($data['items'] as $row) {

                // داخل storeErp() أثناء loop على items

                $product = Product::lockForUpdate()->findOrFail($row['product_id']);

                $onHand = (int) $product->stock_quantity;

                if ($onHand < (int)$row['quantity']) {
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

                // ✅ خصم من stock_quantity فقط
                $product->decrement('stock_quantity', (int)$row['quantity']);

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
                'data' => $order->load('customer', 'items.product'),
            ], 201);
        });
    }

    public function confirm(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        return DB::transaction(function () use ($id, $companyId) {

            $order = Order::where('company_id', $companyId)
                ->lockForUpdate()
                ->with(['items', 'invoice'])
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

            // ✅ هنا السبب اللي بيطلع عندك
            if ($order->items->isEmpty()) {
                return response()->json([
                    'msg' => 'Order has no items',
                ], 422);
            }

            $total = $order->items->sum(fn($item) => $item->quantity * $item->unit_price);

            $order->update(['status' => 'confirmed', 'total' => $total]);

            $invoice = Invoice::create([
                'company_id'   => $companyId,
                'number'       => 'INV-' . now()->format('YmdHis') . '-' . $order->id,
                'order_id'     => $order->id,
                'customer_id'  => $order->customer_id,
                'total'        => $total,
                'status'       => 'unpaid',
                'issued_at'    => now(),
            ]);

            CustomerLedgerEntry::create([
                'company_id'  => $companyId,
                'customer_id' => $invoice->customer_id,
                'invoice_id'  => $invoice->id,
                'type'        => 'invoice',
                'debit'       => $invoice->total,
                'credit'      => 0,
                'entry_date'  => now()->toDateString(),
                'description' => 'Invoice ' . $invoice->number,
            ]);
            $arAccount = Account::where('company_id', $companyId)->where('code', '1100')->firstOrFail();
            $salesAccount = Account::where('company_id', $companyId)->where('code', '4000')->firstOrFail();

            AccountingService::createEntry(
                $invoice, // source = invoice
                'Invoice issued #' . $invoice->number,
                [
                    [
                        'account_id' => $arAccount->id,
                        'debit'      => $invoice->total,
                        'credit'     => 0,
                    ],
                    [
                        'account_id' => $salesAccount->id,
                        'debit'      => 0,
                        'credit'     => $invoice->total,
                    ],
                ],
                $request->user()->id ?? null,
                now()->toDateString()
            );
            foreach ($order->items as $item) {
                InvoiceItem::create([
                    'company_id' => $companyId,
                    'invoice_id' => $invoice->id,
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total'      => $item->quantity * $item->unit_price,
                ]);
            }

            activity('order.confirmed', $order, [], $companyId);

            return response()->json([
                'msg' => 'Order confirmed and invoice created',
                'invoice_id' => $invoice->id,
            ]);
        });
    }

    public function cancel(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        return DB::transaction(function () use ($request, $id, $companyId) {

            $order = Order::where('company_id', $companyId)
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($id);

            if ($order->status === 'cancelled') {
                return response()->json([
                    'msg' => 'Order already cancelled',
                ], 422);
            }

            foreach ($order->items as $item) {

                $product = Product::lockForUpdate()->findOrFail($item->product_id);

                // ✅ رجّع على stock_quantity فقط
                $product->increment('stock_quantity', (int) $item->quantity);

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

            activity('order.cancelled', $order, [], $companyId);

            return response()->json([
                'msg' => 'Order cancelled and stock restored',
            ]);
        });
    }
}
