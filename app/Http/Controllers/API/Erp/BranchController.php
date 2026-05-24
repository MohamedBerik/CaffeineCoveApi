<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = Tenant::id();

        $branches = Branch::where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'msg' => 'Branches list',
            'status' => 200,
            'data' => $branches
        ]);
    }

    public function show(Request $request, $id)
    {
        $branch = Branch::where('company_id', Tenant::id())->findOrFail($id);
        return response()->json([
            'msg' => 'Branch details',
            'status' => 200,
            'data' => $branch
        ]);
    }

    public function store(Request $request)
    {
        $companyId = Tenant::id();

        // ✅ فحص الحد الأقصى للفروع من الباقة النشطة
        $activeSubscription = Subscription::where('company_id', $companyId)
            ->where('status', 'active')
            ->with('plan')
            ->first();

        if ($activeSubscription && $activeSubscription->plan) {
            $maxBranches = $activeSubscription->plan->max_branches;
            if ($maxBranches !== null) {
                $currentBranchesCount = Branch::where('company_id', $companyId)->count();
                if ($currentBranchesCount >= $maxBranches) {
                    return response()->json([
                        'msg' => 'You have reached the maximum number of branches allowed by your plan.',
                        'status' => 422
                    ], 422);
                }
            }
        }

        $validate = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'slug'      => 'nullable|string|max:255|unique:branches,slug',
            'address'   => 'nullable|string|max:500',
            'phone'     => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'msg' => 'Validation required',
                'status' => 422,
                'data' => $validate->errors()
            ], 422);
        }

        $branch = Branch::create([
            'company_id' => $companyId,
            'name'       => $request->name,
            'slug'       => $request->slug ?? \Str::slug($request->name),
            'address'    => $request->address,
            'phone'      => $request->phone,
            'is_active'  => $request->is_active ?? true,
        ]);

        // ✅ إنشاء المنتجين الأساسيين للفرع الجديد تلقائياً
        Product::create([
            'company_id'     => $companyId,
            'branch_id'      => $branch->id,
            'title_en'       => 'Consultation',
            'title_ar'       => 'استشارة',
            'description_en' => 'Consultation Service',
            'description_ar' => 'خدمة استشارة',
            'unit_price'     => 0,
            'stock_quantity' => 0,
            'quantity'       => 0,
            'category_id'    => 1,
        ]);

        Product::create([
            'company_id'     => $companyId,
            'branch_id'      => $branch->id,
            'title_en'       => 'Treatment',
            'title_ar'       => 'علاج',
            'description_en' => 'Treatment Service',
            'description_ar' => 'خدمة علاج',
            'unit_price'     => 0,
            'stock_quantity' => 0,
            'quantity'       => 0,
            'category_id'    => 1,
        ]);

        return response()->json([
            'msg' => 'Branch created successfully',
            'status' => 201,
            'data' => $branch
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $branch = Branch::where('company_id', Tenant::id())->findOrFail($id);

        $validate = Validator::make($request->all(), [
            'name'      => 'sometimes|required|string|max:255',
            'slug'      => 'nullable|string|max:255|unique:branches,slug,' . $branch->id,
            'address'   => 'nullable|string|max:500',
            'phone'     => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'msg' => 'Validation required',
                'status' => 422,
                'data' => $validate->errors()
            ], 422);
        }

        $branch->update($request->only(['name', 'slug', 'address', 'phone', 'is_active']));

        return response()->json([
            'msg' => 'Branch updated successfully',
            'status' => 200,
            'data' => $branch
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $branch = Branch::where('company_id', Tenant::id())->findOrFail($id);
        $branch->delete();

        return response()->json([
            'msg' => 'Branch deleted successfully',
            'status' => 200,
            'data' => null
        ]);
    }
}
