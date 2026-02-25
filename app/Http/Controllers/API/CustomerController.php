<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $customers = Customer::where('company_id', $companyId)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            "msg" => "Customers fetched successfully",
            "status" => 200,
            "data" => CustomerResource::collection($customers),
        ], 200);
    }

    public function show(Request $request, Customer $customer)
    {
        $companyId = $request->user()->company_id;

        if ($customer->company_id !== $companyId) {
            return response()->json([
                "msg" => "Customer not found",
                "status" => 404,
                "data" => null
            ], 404);
        }

        return response()->json([
            "msg" => "Customer fetched successfully",
            "status" => 200,
            "data" => new CustomerResource($customer),
        ], 200);
    }

    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'name'     => ['required', 'string', 'min:3', 'max:255'],
            'email'    => ['nullable', 'email', 'max:255'],
            // optional password (لو هتعمل patient portal لاحقًا)
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'status'   => ['nullable', Rule::in([0, 1])],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $customer = Customer::create(array_merge($data, [
            'company_id' => $companyId,
        ]));

        return response()->json([
            "msg" => "Customer created successfully",
            "status" => 201,
            "data" => new CustomerResource($customer),
        ], 201);
    }

    public function update(Request $request, Customer $customer)
    {
        $companyId = $request->user()->company_id;

        if ($customer->company_id !== $companyId) {
            return response()->json([
                "msg" => "Customer not found",
                "status" => 404,
                "data" => null
            ], 404);
        }

        $data = $request->validate([
            'name'     => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'email'    => ['nullable', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'status'   => ['nullable', Rule::in([0, 1])],
        ]);

        // update password only if provided (and not empty)
        if (array_key_exists('password', $data)) {
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
        }

        $customer->update($data);

        return response()->json([
            "msg" => "Customer updated successfully",
            "status" => 200,
            "data" => new CustomerResource($customer->fresh()),
        ], 200);
    }

    public function destroy(Request $request, Customer $customer)
    {
        $companyId = $request->user()->company_id;

        if ($customer->company_id !== $companyId) {
            return response()->json([
                "msg" => "Customer not found",
                "status" => 404,
                "data" => null
            ], 404);
        }

        $customer->delete();

        return response()->json([
            "msg" => "Customer deleted successfully",
            "status" => 200,
            "data" => null
        ], 200);
    }
}
