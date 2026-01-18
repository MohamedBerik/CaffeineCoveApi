<?php

namespace App\Http\Controllers\API;

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
        if($order){
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
            'id' => 'required|unique:categories|max:20',
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
}
