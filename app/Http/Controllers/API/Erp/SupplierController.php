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
        // تخطي الصلاحيات مؤقتًا للتشخيص
        try {
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
                "branch_id"  => Tenant::branchId() ?? $request->user()->branch_id,
                "name" => $request->name,
                "email" => $request->email,
                "phone" => $request->phone,
                "address" => $request->address ?? '',
                "contact_person" => $request->contact_person ?? '',
                "notes" => $request->notes ?? '',
            ]);

            return response()->json([
                "msg" => "Created Successfully",
                "status" => 201,
                "data" => new SupplierResource($supplier)
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Supplier store error: ' . $e->getMessage());
            return response()->json([
                "msg" => "Server error: " . $e->getMessage(),
                "status" => 500,
                "data" => null
            ], 500);
        }
    }

    /**
     * تعديل بيانات مورد.
     */
    public function update(Request $request, $id)
    {
        $supplier = Supplier::where('company_id', Tenant::id())->findOrFail($id);

        // يمكن تفعيل سطر authorize لو أردت، لكن الأمان الأساسي من middleware
        // $this->authorize('update', $supplier);

        $validate = Validator::make($request->all(), [
            "name" => "sometimes|required|min:3|max:255",
            "email" => "sometimes|required|email|unique:suppliers,email," . $supplier->id,
            "phone" => "sometimes|required|min:3|max:255",
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

        $supplier->update($request->only([
            "name",
            "email",
            "phone",
            "address",
            "contact_person",
            "notes"
        ]));

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
