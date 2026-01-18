<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    function index()
    {
        $sale = SaleResource::collection(Sale::all());
        $data = [
            "msg" => "Return All Data From Sale Table",
            "status" => 200,
            "data" => $sale
        ];
        return response()->json($data);
    }
    function show($id)
    {
        $sale = Sale::find($id);

        if ($sale) {
            $data = [
                "msg" => "Return One Record of Sale Table",
                "status" => 200,
                "data" => new SaleResource($sale)
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
        $sale = Sale::find($id);
        if($sale){
            $sale->delete();
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
            'id' => 'required|unique:sales|max:20',
            'title_en' => 'required|min:3|max:255',
            'title_ar' => 'required|min:3|max:255',
            'description_en' => 'required|min:3|max:255',
            'description_ar' => 'required|min:3|max:255',
            'price' => 'required',
            'quantity' => 'required',
            'employee_id' => 'required',
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }

        $sale = Sale::create([
            "id" => $request->id,
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
            "price" => $request->price,
            "quantity" => $request->quantity,
            "employee_id" => $request->employee_id,
        ]);
        $data = [
            "msg" => "Created Successfully",
            "status" => 200,
            "data" => new SaleResource($sale)
        ];
        return response()->json($data);
    }
    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $sale = Sale::find($old_id);

        $validate = Validator::make($request->all(), [
            "id" => ['required', Rule::unique('sales')->ignore($old_id)],
            "title_en" => "required|min:3|max:255",
            "title_ar" => "required|min:3|max:255",
            "description_en" => "required|min:3|max:255",
            "description_ar" => "required|min:3|max:255",
            "price" => "required",
            "quantity" => "required",
            "employee_id" => "required",
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }






        if ($sale) {

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
            $data = [
                "msg" => "Updated Successfully",
                "status" => 200,
                "data" => new SaleResource($sale)
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
