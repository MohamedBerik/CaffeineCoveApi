<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Supply;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Supply::query()->orderByDesc('id');

        if (!$user->is_super_admin && $user->branch_id !== null) {
            $query->where('branch_id', $user->branch_id);
        }

        $supplies = $query->paginate((int)$request->get('per_page', 20));

        return response()->json([
            "msg" => "Supplies list",
            "status" => 200,
            "data" => $supplies
        ]);
    }

    public function show(Request $request, $id)
    {
        $supply = Supply::where('company_id', Tenant::id())->findOrFail($id);
        return response()->json([
            "msg" => "Supply details",
            "status" => 200,
            "data" => $supply
        ]);
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:supplies,sku',
            'unit_cost' => 'required|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 422,
                "data" => $validate->errors()
            ], 422);
        }

        $supply = Supply::create([
            "company_id" => Tenant::id(),
            "branch_id" => Tenant::branchId() ?? $request->user()->branch_id,
            "name" => $request->name,
            "sku" => $request->sku,
            "unit_cost" => $request->unit_cost,
            "stock_quantity" => $request->stock_quantity ?? 0,
            "category_id" => $request->category_id,
            "supplier_id" => $request->supplier_id,
        ]);

        return response()->json([
            "msg" => "Supply created successfully",
            "status" => 201,
            "data" => $supply
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $supply = Supply::where('company_id', Tenant::id())->findOrFail($id);

        $validate = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:supplies,sku,' . $supply->id,
            'unit_cost' => 'sometimes|required|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 422,
                "data" => $validate->errors()
            ], 422);
        }

        $supply->update($request->only(['name', 'sku', 'unit_cost', 'stock_quantity', 'category_id', 'supplier_id']));

        return response()->json([
            "msg" => "Supply updated successfully",
            "status" => 200,
            "data" => $supply
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $supply = Supply::where('company_id', Tenant::id())->findOrFail($id);
        $supply->delete();

        return response()->json([
            "msg" => "Supply deleted successfully",
            "status" => 200,
            "data" => null
        ]);
    }
}
