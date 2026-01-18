<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index()
    {
        $sale = DB::table("sales")->select("id","price","quantity","employee_id","title_".app()->getLocale() . " as title","description_".app()->getLocale(). " as description")->get();
        return view('sale.index', ["result" => $sale]);
    }

    public function show($id)
    {
        $sale = Sale::findOrFail($id);
        return view("Sale.show", ["result" => $sale]);
    }

    public function delete($id)
    {

        $sale = Sale::findOrFail($id);
        $sale->delete();
        return redirect()->route("dashboard")->with("salemessage", "Deleted Successfully");
    }

    public function create()
    {
        return view("Sale.create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|unique:sales|max:20',
            'title_en' => 'required|min:3|max:255',
            'title_ar' => 'required|min:3|max:255',
            'description_en' => 'required|min:3|max:255',
            'description_ar' => 'required|min:3|max:255',
            'price' => 'required',
            'quantity' => 'required',
            'employee_id' => 'required',
        ]);

        Sale::create([
            "id" => $request->id,
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
            "price" => $request->price,
            "quantity" => $request->quantity,
            "employee_id" => $request->employee_id,
        ]);
        return redirect()->route("dashboard")->with("salemessage", "Created Successfully");
    }

    public function edit($id)
    {
        $sale = Sale::findOrFail($id);
        return view("Sale.edit", ["result" => $sale]);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $sale = Sale::findOrFail($old_id);

        $request->validate([
            "id" => ['required', Rule::unique('sales')->ignore($old_id)],
            "title_en" => "required|min:3|max:255",
            "title_ar" => "required|min:3|max:255",
            "description_en" => "required|min:3|max:255",
            "description_ar" => "required|min:3|max:255",
            "price" => "required",
            "quantity" => "required",
            "employee_id" => "required",
        ]);

        $sale->update([
            "id" => $request->id,
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
            "price" => $request->price,
            "quantity" => $request->quantity,
            "employee_id" => $request->employee_id,
        ]);
        return redirect()->route("dashboard")->with("salemessage", "Updated Successfully");
    }
}
