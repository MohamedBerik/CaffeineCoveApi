<?php

namespace App\Http\Controllers\API;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    function index()
    {
        $order = OrderResource::collection(Order::all());
        $data = [
            "msg" => "Return All Data From Order Table",
            "status" => 200,
            "data" => $order
        ];
        return response()->json($data);
    }
    function show($id)
    {
        $order = Order::find($id);

        if ($order) {
            $data = [
                "msg" => "Return One Record of Order Table",
                "status" => 200,
                "data" => new OrderResource($order)
            ];
            return response()->json($data);
        } else {
            $data = [
                "msg" => "No Such id",
                "status" => 205,
                "data" => null
            ];
            return response()->json($data);
        }
    }
    function delete(Request $request)
    {
        $id = $request->id;
        $order = Order::find($id);
        if ($order) {
            $order->delete();
            $data = [
                "msg" => "Deleted Successfully",
                "status" => 200,
                "data" => null
            ];
            return response()->json($data);
        } else {
            $data = [
                "msg" => "No Such id",
                "status" => 205,
                "data" => null
            ];
            return response()->json($data);
        }
    }
    public function store(Request $request)
    {

        $validate = Validator::make($request->all(), [
            'id' => 'required|unique:orders|max:20',
            'title_en' => 'required|min:3|max:255',
            'title_ar' => 'required|min:3|max:255',
            'description_en' => 'required|min:3|max:255',
            'description_ar' => 'required|min:3|max:255',
            'price' => 'required',
            'quantity' => 'required',
            'customer_id' => 'required',
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }

        $order = Order::create([
            "id" => $request->id,
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
            "price" => $request->price,
            "quantity" => $request->quantity,
            "customer_id" => $request->customer_id,
        ]);
        $data = [
            "msg" => "Created Successfully",
            "status" => 200,
            "data" => new OrderResource($order)
        ];
        return response()->json($data);
    }
    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $order = Order::find($old_id);

        $validate = Validator::make($request->all(), [
            "id" => ['required', Rule::unique('orders')->ignore($old_id)],
            "title_en" => "required|min:3|max:255",
            "title_ar" => "required|min:3|max:255",
            "description_en" => "required|min:3|max:255",
            "description_ar" => "required|min:3|max:255",
            "price" => "required",
            "quantity" => "required",
            "customer_id" => "required",
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }






        if ($order) {

            $order->update([
                "id" => $request->id,
                "title_en" => $request->title_en,
                "title_ar" => $request->title_ar,
                "description_en" => $request->description_en,
                "description_ar" => $request->description_ar,
                "price" => $request->price,
                "quantity" => $request->quantity,
                "customer_id" => $request->customer_id,
            ]);
            $data = [
                "msg" => "Updated Successfully",
                "status" => 200,
                "data" => new OrderResource($order)
            ];
            return response()->json($data);
        } else {
            $data = [
                "msg" => "No such id",
                "status" => 205,
                "data" => null
            ];
            return response()->json($data);
        }
    }
    public function storeErp(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($data, $request) {

            // 👇 إنشاء الطلب مع قيم افتراضية للأعمدة المطلوبة
            $order = Order::create([
                'customer_id'    => $data['customer_id'],
                'status'         => 'pending',
                'total'          => 0,
                'created_by'     => $request->user()->id,
                'title_en'       => 'ERP Order',
                'title_ar'       => 'طلب ERP',
                'description_en' => '',
                'description_ar' => '',
            ]);

            $total = 0;

            foreach ($data['items'] as $item) {

                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                // منع البيع لو المخزون لا يكفي
                if ($product->stock_quantity < $item['quantity']) {
                    abort(422, "Insufficient stock for product {$product->id}");
                }

                $lineTotal = $product->price * $item['quantity'];

                // إنشاء OrderItem
                OrderItem::create([
                    'order_id'    => $order->id,
                    'product_id'  => $product->id,
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $product->price,
                    'total'       => $lineTotal,
                ]);

                // خصم من المخزون
                $product->decrement('stock_quantity', $item['quantity']);

                // تسجيل حركة المخزون
                StockMovement::create([
                    'product_id'     => $product->id,
                    'type'           => 'out',
                    'quantity'       => $item['quantity'],
                    'reference_type' => Order::class,
                    'reference_id'   => $order->id,
                    'created_by'     => $request->user()->id,
                ]);

                $total += $lineTotal;
            }

            // تحديث إجمالي الطلب
            $order->update(['total' => $total]);

            return response()->json([
                'msg'  => 'Order created (ERP)',
                'data' => $order->load('items.product')
            ], 201);
        });
    }
    public function confirm($id)
    {
        $order = Order::with('items')->findOrFail($id);

        if ($order->status === 'confirmed') {
            return response()->json([
                'msg' => 'Order already confirmed'
            ], 422);
        }

        return DB::transaction(function () use ($order) {

            $order->update([
                'status' => 'confirmed'
            ]);
            activity('order.confirmed', $order);

            $invoice = Invoice::create([
                'number'      => 'INV-' . now()->format('YmdHis') . '-' . $order->id,
                'order_id'    => $order->id,
                'customer_id' => $order->customer_id,
                'total'       => $order->total,
                'status'      => 'unpaid',
                'issued_at'   => now(),
            ]);

            foreach ($order->items as $item) {

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total'      => $item->total,
                ]);
            }

            return response()->json([
                'msg' => 'Order confirmed and invoice created',
                'invoice_id' => $invoice->id
            ]);
        });
    }
    public function cancel(Request $request, $id)
    {
        $order = Order::with('items')->findOrFail($id);

        if ($order->status === 'cancelled') {
            return response()->json([
                'msg' => 'Order already cancelled'
            ], 422);
        }

        return DB::transaction(function () use ($order, $request) {

            foreach ($order->items as $item) {

                $product = Product::lockForUpdate()
                    ->findOrFail($item->product_id);

                // إعادة الكمية
                $product->increment('stock_quantity', $item->quantity);

                // حركة مخزون
                StockMovement::create([
                    'product_id'   => $product->id,
                    'type'         => 'in',
                    'quantity'     => $item->quantity,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'created_by'   => $request->user()->id,
                ]);
            }

            $order->update([
                'status' => 'cancelled'
            ]);
            activity('order.cancelled', $order);

            return response()->json([
                'msg' => 'Order cancelled and stock restored'
            ]);
        });
    }
    public function createInvoice($id)
    {
        $order = Order::with('items')->findOrFail($id);

        // تمنع إنشاء فاتورة إذا فاتورة موجودة بالفعل
        if ($order->invoice) {
            return response()->json([
                'message' => 'Invoice already exists'
            ], 422);
        }

        // تمنع إنشاء فاتورة على طلب فاضي
        if ($order->items->isEmpty()) {
            return response()->json([
                'message' => 'Order has no items'
            ], 422);
        }

        // حساب الإجمالي من الـ items
        $total = $order->items->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });

        // إنشاء الفاتورة
        $invoice = Invoice::create([
            'number'      => 'INV-' . now()->format('Ymd') . '-' . str_pad($order->id, 5, '0', STR_PAD_LEFT),
            'order_id'    => $order->id,
            'customer_id' => $order->customer_id,
            'total'       => $total,
            'status'      => 'unpaid',
            'issued_at'   => now(),
        ]);

        // نسخ الـ items إلى InvoiceItem
        foreach ($order->items as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $item->product_id,
                'quantity'   => $item->quantity,
                'unit_price' => $item->unit_price,
                'total'      => $item->quantity * $item->unit_price,
            ]);
        }

        return response()->json([
            'message' => 'Invoice created',
            'invoice' => $invoice
        ]);
    }
}
