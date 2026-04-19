<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Supplier::class, 'supplier');
    }

    public function index(Request $request)
    {
        $suppliers = SupplierResource::collection(
            Supplier::query()->get()
        );

        return response()->json([
            "msg" => "Return All Data From Supplier Table",
            "status" => 200,
            "data" => $suppliers
        ]);
    }

    public function show(Request $request, $id)
    {
        $supplier = Supplier::query()->find($id);

        if ($supplier) {
            return response()->json([
                "msg" => "Return One Record of Supplier Table",
                "status" => 200,
                "data" => new SupplierResource($supplier)
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
        $supplier = Supplier::query()->find($id);

        if ($supplier) {
            $supplier->delete();

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
            'name' => 'required|min:3|max:255',
            'email' => 'required|email|unique:suppliers,email',
            'phone' => 'required|min:3|max:255',
            'address' => 'nullable|string|max:500',
            'contact_person' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 422,
                "data" => $validate->errors()
            ], 422);
        }

        $supplier = Supplier::create([
            "company_id" => Tenant::id(),
            "name" => $request->name,
            "email" => $request->email,
            "phone" => $request->phone,
            "address" => $request->address,
            "contact_person" => $request->contact_person,
            "notes" => $request->notes,
        ]);

        return response()->json([
            "msg" => "Created Successfully",
            "status" => 201,
            "data" => new SupplierResource($supplier)
        ], 201);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $supplier = Supplier::query()->find($old_id);

        if (!$supplier) {
            return response()->json([
                "msg" => "No such id",
                "status" => 404,
                "data" => null
            ], 404);
        }

        $validate = Validator::make($request->all(), [
            "name" => "required|min:3|max:255",
            "email" => "required|email|unique:suppliers,email," . $old_id,
            "phone" => "required|min:3|max:255",
            "address" => "nullable|string|max:500",
            "contact_person" => "nullable|string|max:255",
            "notes" => "nullable|string",
        ]);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 422,
                "data" => $validate->errors()
            ], 422);
        }

        $supplier->update([
            "name" => $request->name,
            "email" => $request->email,
            "phone" => $request->phone,
            "address" => $request->address,
            "contact_person" => $request->contact_person,
            "notes" => $request->notes,
        ]);

        return response()->json([
            "msg" => "Updated Successfully",
            "status" => 200,
            "data" => new SupplierResource($supplier->fresh())
        ]);
    }
}
