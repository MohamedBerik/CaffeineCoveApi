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
    /**
     * عرض قائمة الموردين.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Supplier::query()->orderByDesc('id');

        // عزل الفروع لغير المشرفين
        if (!$user->is_super_admin && $user->branch_id !== null) {
            $query->where('branch_id', $user->branch_id);
        }

        $suppliers = SupplierResource::collection(
            $query->paginate((int) $request->get('per_page', 20))
        );

        return response()->json([
            "msg" => "Suppliers list",
            "status" => 200,
            "data" => $suppliers
        ]);
    }

    /**
     * عرض تفاصيل مورد واحد.
     */
    public function show(Request $request, $id)
    {
        $supplier = Supplier::where('company_id', Tenant::id())->findOrFail($id);

        return response()->json([
            "msg" => "Supplier details",
            "status" => 200,
            "data" => new SupplierResource($supplier)
        ]);
    }

    /**
     * إضافة مورد جديد.
     */
    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required|min:3|max:255',
            'email' => 'required|email|unique:suppliers,email',
            'phone' => 'required|min:3|max:255',
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
            "branch_id"  => Tenant::branchId() ?? $request->user()->branch_id,
            "name" => $request->name,
            "email" => $request->email,
            "phone" => $request->phone,
        ]);

        return response()->json([
            "msg" => "Supplier created successfully",
            "status" => 201,
            "data" => new SupplierResource($supplier)
        ], 201);
    }
    /**
     * تعديل بيانات مورد.
     */
    public function update(Request $request, $id)
    {
        $supplier = Supplier::where('company_id', Tenant::id())->findOrFail($id);

        $validate = Validator::make($request->all(), [
            "name" => "required|min:3|max:255",
            "email" => "required|email|unique:suppliers,email," . $supplier->id,
            "phone" => "required|min:3|max:255",
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
        ]);

        return response()->json([
            "msg" => "Supplier updated successfully",
            "status" => 200,
            "data" => new SupplierResource($supplier->fresh())
        ]);
    }

    /**
     * حذف مورد.
     */
    public function destroy(Request $request, $id)
    {
        $supplier = Supplier::where('company_id', Tenant::id())->findOrFail($id);
        $supplier->delete();

        return response()->json([
            "msg" => "Supplier deleted successfully",
            "status" => 200,
            "data" => null
        ]);
    }
}
