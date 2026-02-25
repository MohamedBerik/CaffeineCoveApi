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
            ->paginate(20);

        return response()->json([
            'msg' => 'Customers list',
            'status' => 200,
            'data' => CustomerResource::collection($customers),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ]
        ]);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $customer = Customer::where('company_id', $companyId)->find($id);

        if (!$customer) {
            return response()->json([
                'msg' => 'Customer not found',
                'status' => 404,
                'data' => null
            ], 404);
        }

        return response()->json([
            'msg' => 'Customer details',
            'status' => 200,
            'data' => new CustomerResource($customer)
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'name'  => ['required', 'string', 'min:3', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                // لو عايز تمنع تكرار نفس الإيميل داخل نفس الشركة فقط
                Rule::unique('customers', 'email')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            // Customer عادة مش محتاج password. نخليه اختياري (لو ناوي تعمل portal لاحقًا)
            'password' => ['nullable', 'string', 'min:6', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $customer = Customer::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'password' => !empty($data['password']) ? Hash::make($data['password']) : null,
            'status' => $data['status'] ?? 1, // عدّل حسب جدولك (string/boolean)
            'phone' => $data['phone'] ?? null,
        ]);

        return response()->json([
            'msg' => 'Created successfully',
            'status' => 201,
            'data' => new CustomerResource($customer)
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $customer = Customer::where('company_id', $companyId)->find($id);

        if (!$customer) {
            return response()->json([
                'msg' => 'Customer not found',
                'status' => 404,
                'data' => null
            ], 404);
        }

        $data = $request->validate([
            'name'  => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->where(fn($q) => $q->where('company_id', $companyId))
                    ->ignore($customer->id),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:6', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        if (array_key_exists('name', $data)) {
            $customer->name = $data['name'];
        }
        if (array_key_exists('email', $data)) {
            $customer->email = $data['email'];
        }
        if (array_key_exists('status', $data)) {
            $customer->status = $data['status'] ?? $customer->status;
        }
        if (array_key_exists('phone', $data)) {
            $customer->phone = $data['phone'];
        }
        if (array_key_exists('password', $data)) {
            $customer->password = !empty($data['password']) ? Hash::make($data['password']) : null;
        }

        $customer->save();

        return response()->json([
            'msg' => 'Updated successfully',
            'status' => 200,
            'data' => new CustomerResource($customer)
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $customer = Customer::where('company_id', $companyId)->find($id);

        if (!$customer) {
            return response()->json([
                'msg' => 'Customer not found',
                'status' => 404,
                'data' => null
            ], 404);
        }

        $customer->delete();

        return response()->json([
            'msg' => 'Deleted successfully',
            'status' => 200,
            'data' => null
        ]);
    }
}
