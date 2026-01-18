<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index()
    {
        $order = Order::all();
        return view('order.index', ["result" => $order]);
    }

    public function show($id)
    {
        $order = Order::findOrFail($id);
        return view("Order.show", ["result" => $order]);
    }

    public function delete($id)
    {

        $order = Order::findOrFail($id);
        $order->delete();
        return redirect()->route("dashboard")->with("ordermessage", "Deleted Successfully");
    }

    public function create()
    {
        return view("Order.create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|unique:orders|max:20',
            'title_en' => 'required|min:3|max:255',
            'title_ar' => 'required|min:3|max:255',
            'description_en' => 'required|min:3|max:255',
            'description_ar' => 'required|min:3|max:255',
            'price' => 'required',
            'quantity' => 'required',
            'customer_id' => 'required',
        ]);

        Order::create([
            "id" => $request->id,
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
            "price" => $request->price,
            "quantity" => $request->quantity,
            "customer_id" => $request->customer_id,
        ]);
        return redirect()->route("dashboard")->with("ordermessage", "Created Successfully");
    }

    public function edit($id)
    {
        $order = Order::findOrFail($id);
        return view("Order.edit", ["result" => $order]);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $order = Order::findOrFail($old_id);

        $request->validate([
            "id" => ['required', Rule::unique('orders')->ignore($old_id)],
            "title_en" => "required|min:3|max:255",
            "title_ar" => "required|min:3|max:255",
            "description_en" => "required|min:3|max:255",
            "description_ar" => "required|min:3|max:255",
            "price" => "required",
            "quantity" => "required",
            "customer_id" => "required",
        ]);

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
        return redirect()->route("dashboard")->with("ordermessage", "Updated Successfully");
    }
}
