<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Customer::class, 'customer', ['except' => ['index', 'store']]);
    }

    public function index(Request $request)
    {
        $q = Customer::query()->orderByDesc('id');

        if ($search = trim((string) $request->get('search', ''))) {
            $q->where(function ($x) use ($search) {
                $x->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('patient_code', 'like', "%{$search}%");
            });
        }

        $customers = $q->paginate((int) $request->get('per_page', 20));

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
        $customer = Customer::query()->find($id);

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
        $companyId = Tenant::id();
        $branchId = (int) $request->header('X-Branch-ID') ?: Tenant::branchId();

        // ✅ حماية إضافية: إذا كان branchId لازال null نُعيد 400
        if (!$branchId) {
            return response()->json(['msg' => 'Branch is required'], 400);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')],
            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['0', '1'])],
        ]);

        // ✅ تثبيت الـ branch في الـ Tenant Context لتفادي أي mismatch
        if ($branchId) {
            app()->instance('tenant_branch_id', $branchId);
        }

        $customer = DB::transaction(function () use ($companyId, $data, $branchId) {
            $patientCode = $this->generateNextPatientCode($companyId);

            return Customer::create([
                'company_id' => $companyId,
                'name'       => $data['name'],
                'email'      => $data['email'] ?? null,
                'patient_code' => $patientCode,
                'phone'      => $data['phone'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender'     => $data['gender'] ?? null,
                'address'    => $data['address'] ?? null,
                'notes'      => $data['notes'] ?? null,
                'status'     => $data['status'] ?? '1',
            ]);
        });

        // ✅ إعادة تحميل بدون Scopes لتفادي ModelNotFoundException
        $customer = Customer::withoutGlobalScopes()->find($customer->id);

        return response()->json([
            'msg'  => 'Created successfully',
            'status' => 201,
            'data' => new CustomerResource($customer)
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::query()->find($id);

        if (!$customer) {
            return response()->json([
                'msg' => 'Customer not found',
                'status' => 404,
                'data' => null
            ], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', Rule::in(['0', '1'])],
        ]);

        foreach (
            [
                'name',
                'email',
                'phone',
                'date_of_birth',
                'gender',
                'address',
                'notes',
                'status'
            ] as $field
        ) {
            if (array_key_exists($field, $data)) {
                $customer->{$field} = $data[$field];
            }
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
        $customer = Customer::query()->find($id);

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

    private function generateNextPatientCode(int $companyId): string
    {
        $lastCustomer = Customer::query()
            ->whereNotNull('patient_code')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $nextNumber = 1;

        if ($lastCustomer && preg_match('/(\d+)$/', (string) $lastCustomer->patient_code, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return 'PT-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
