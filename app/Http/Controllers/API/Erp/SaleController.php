<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Sale::class, 'sale');
    }

    public function index()
    {
        $sales = SaleResource::collection(
            Sale::query()->get()
        );

        return response()->json([
            "msg" => "Return All Data From Sale Table",
            "status" => 200,
            "data" => $sales
        ]);
    }

    public function show($id)
    {
        $sale = Sale::query()->find($id);

        if ($sale) {
            return response()->json([
                "msg" => "Return One Record of Sale Table",
                "status" => 200,
                "data" => new SaleResource($sale)
            ]);
        }

        return response()->json([
            "msg" => "No Such id",
            "status" => 404,
            "data" => null
        ], 404);
    }

    public function delete(Request $request)
    {
        $id = $request->id;
        $sale = Sale::query()->find($id);

        if ($sale) {
            $sale->delete();

            return response()->json([
                "msg" => "Deleted Successfully",
                "status" => 200,
                "data" => null
            ]);
        }

        return response()->json([
            "msg" => "No Such id",
            "status" => 404,
            "data" => null
        ], 404);
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'title_en' => 'required|min:3|max:255',
            'title_ar' => 'required|min:3|max:255',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'employee_id' => 'required|exists:employees,id',
        ]);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 422,
                "data" => $validate->errors()
            ], 422);
        }

        $sale = Sale::create([
            "company_id" => Tenant::id(),
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
            "price" => $request->price,
            "quantity" => $request->quantity,
            "employee_id" => $request->employee_id,
        ]);

        return response()->json([
            "msg" => "Created Successfully",
            "status" => 201,
            "data" => new SaleResource($sale)
        ], 201);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $sale = Sale::query()->find($old_id);

        if (!$sale) {
            return response()->json([
                "msg" => "No such id",
                "status" => 404,
                "data" => null
            ], 404);
        }

        $validate = Validator::make($request->all(), [
            "title_en" => "required|min:3|max:255",
            "title_ar" => "required|min:3|max:255",
            "description_en" => "nullable|string",
            "description_ar" => "nullable|string",
            "price" => "required|numeric|min:0",
            "quantity" => "required|integer|min:1",
            "employee_id" => "required|exists:employees,id",
        ]);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 422,
                "data" => $validate->errors()
            ], 422);
        }

        $sale->update([
            "title_en" => $request->title_en,
            "title_ar" => $request->title_ar,
            "description_en" => $request->description_en,
            "description_ar" => $request->description_ar,
            "price" => $request->price,
            "quantity" => $request->quantity,
            "employee_id" => $request->employee_id,
        ]);

        return response()->json([
            "msg" => "Updated Successfully",
            "status" => 200,
            "data" => new SaleResource($sale->fresh())
        ]);
    }
}
