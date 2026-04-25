<?php

namespace App\Http\Controllers\API\SaaS;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $plans = Plan::orderBy('price_monthly')->get();

        // ✅ تأكد إن features راجعة كـ Array
        $plans->transform(function ($plan) {
            if (is_string($plan->features)) {
                $plan->features = json_decode($plan->features, true);
            }
            return $plan;
        });

        return response()->json([
            'msg' => 'Plans list',
            'status' => 200,
            'data' => $plans,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['nullable', 'numeric', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_patients' => ['nullable', 'integer', 'min:0'],
            'max_appointments' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $plan = Plan::create($data);

        return response()->json([
            'msg' => 'Plan created successfully',
            'status' => 201,
            'data' => $plan,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $plan = Plan::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'price_monthly' => ['sometimes', 'numeric', 'min:0'],
            'price_yearly' => ['nullable', 'numeric', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_patients' => ['nullable', 'integer', 'min:0'],
            'max_appointments' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $plan->update($data);

        return response()->json([
            'msg' => 'Plan updated successfully',
            'status' => 200,
            'data' => $plan,
        ]);
    }

    public function destroy($id)
    {
        $plan = Plan::findOrFail($id);
        $plan->delete();

        return response()->json([
            'msg' => 'Plan deleted successfully',
            'status' => 200,
        ]);
    }

    public function toggle($id)
    {
        $plan = Plan::findOrFail($id);
        $plan->update(['is_active' => !$plan->is_active]);

        return response()->json([
            'msg' => 'Plan status updated',
            'status' => 200,
        ]);
    }
}
