<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Employee::class, 'employee', ['except' => ['index', 'show', 'update']]);
    }

    public function index(Request $request)
    {
        $employees = EmployeeResource::collection(
            Employee::query()->get()
        );

        return response()->json([
            "msg" => "Return All Data From Employee Table",
            "status" => 200,
            "data" => $employees
        ]);
    }

    public function show(Request $request, $id)
    {
        $employee = Employee::query()->find($id);

        if ($employee) {
            return response()->json([
                "msg" => "Return One Record of Employee Table",
                "status" => 200,
                "data" => new EmployeeResource($employee)
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
        $employee = Employee::query()->find($id);

        if ($employee) {
            $employee->delete();

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
            'name'      => 'required|min:3|max:255',
            'email'     => 'required|email|unique:users,email',     // ✅ تحقق على users
            'password'  => 'required|min:6|max:255',
            'salary'    => 'nullable|numeric|min:0',
            'branch_id' => 'nullable|integer|exists:branches,id',   // ✅ اختيار الفرع
        ]);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 422,
                "data" => $validate->errors()
            ], 422);
        }

        $companyId = Tenant::id();
        $branchId  = $request->branch_id ?? null;

        // 1) إنشاء User بدور receptionist
        $user = User::create([
            'company_id'     => $companyId,
            'branch_id'      => $branchId,
            'name'           => $request->name,
            'email'          => $request->email,
            'password'       => bcrypt($request->password),
            'role'           => 'receptionist',
            'status'         => 1,
            'is_super_admin' => false,
        ]);

        $employee = Employee::create([
            'company_id' => $companyId,
            'branch_id'  => $branchId,
            'user_id'    => $user->id,          // ✅ الربط مع users
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'salary'     => $request->salary ?? 0,
        ]);

        return response()->json([
            "msg" => "Created Successfully",
            "status" => 201,
            "data" => new EmployeeResource($employee)
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::query()->with('user')->find($id);

        if (!$employee) {
            return response()->json([
                "msg"    => "No such id",
                "status" => 404,
                "data"   => null
            ], 404);
        }

        // ✅ حماية: لا يمكن تحديث employees بدون user مرتبط (لأننا نزامن)
        if (!$employee->user) {
            return response()->json([
                "msg"    => "This employee is not linked to a user account",
                "status" => 422,
                "data"   => null
            ], 422);
        }

        $rules = [
            "name"   => "required|min:3|max:255",
            "email"  => "required|email|unique:users,email," . $employee->user->id, // ✅ التحقق على users
            "salary" => "nullable|numeric|min:0",
            "branch_id" => "nullable|integer|exists:branches,id",
        ];

        if ($request->filled('password')) {
            $rules['password'] = 'min:6|max:255';
        }

        $validate = Validator::make($request->all(), $rules);

        if ($validate->fails()) {
            return response()->json([
                "msg"    => "Validation required",
                "status" => 422,
                "data"   => $validate->errors()
            ], 422);
        }

        // 1. تحديث بيانات User المرتبط
        $user = $employee->user;
        $user->name  = $request->name;
        $user->email = $request->email;
        if ($request->filled('branch_id')) {
            $user->branch_id = $request->branch_id;
        }
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->save();

        // 2. تحديث بيانات Employee
        $employee->name  = $request->name;
        $employee->email = $request->email;
        if ($request->filled('branch_id')) {
            $employee->branch_id = $request->branch_id;
        }
        $employee->salary = $request->salary ?? $employee->salary;
        if ($request->filled('password')) {
            $employee->password = Hash::make($request->password);
        }
        $employee->save();

        return response()->json([
            "msg"    => "Updated Successfully",
            "status" => 200,
            "data"   => new EmployeeResource($employee->fresh()->load('user'))
        ]);
    }
}
