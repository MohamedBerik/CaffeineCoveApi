<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $employee = EmployeeResource::collection(
            Employee::where('company_id', $companyId)->get()
        );

        return response()->json([
            "msg" => "Return All Data From Employee Table",
            "status" => 200,
            "data" => $employee
        ]);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $employee = Employee::where('company_id', $companyId)
            ->find($id);

        if ($employee) {
            return response()->json([
                "msg" => "Return One Record of Employee Table",
                "status" => 200,
                "data" => new EmployeeResource($employee)
            ]);
        }

        return response()->json([
            "msg" => "No Such id",
            "status" => 205,
            "data" => null
        ]);
    }

    public function delete(Request $request)
    {
        $companyId = $request->user()->company_id;
        $id = $request->id;

        $employee = Employee::where('company_id', $companyId)
            ->find($id);

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
            "status" => 205,
            "data" => null
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $validate = Validator::make($request->all(), [
            'id'       => [
                'required',
                'max:20',
                Rule::unique('employees')->where(
                    fn($q) =>
                    $q->where('company_id', $companyId)
                ),
            ],
            'name'     => 'required|min:3|max:255',
            'email'    => 'required|min:3|max:255',
            'password' => 'required|min:3|max:255',
            'salary'   => 'required',
        ]);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ]);
        }

        $employee = Employee::create([
            "id"         => $request->id,
            "company_id" => $companyId,
            "name"       => $request->name,
            "email"      => $request->email,
            "password"   => Hash::make($request->password),
            "salary"     => $request->salary,
        ]);

        return response()->json([
            "msg" => "Created Successfully",
            "status" => 200,
            "data" => new EmployeeResource($employee)
        ]);
    }

    public function update(Request $request)
    {
        $companyId = $request->user()->company_id;
        $old_id = $request->old_id;

        $employee = Employee::where('company_id', $companyId)
            ->find($old_id);

        $validate = Validator::make($request->all(), [
            "id" => [
                'required',
                Rule::unique('employees')
                    ->ignore($old_id)
                    ->where(
                        fn($q) =>
                        $q->where('company_id', $companyId)
                    ),
            ],
            "name"     => "required|min:3|max:255",
            "email"    => "required|min:3|max:255",
            "password" => "required|min:3|max:255",
            "salary"   => "required",
        ]);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ]);
        }

        if (!$employee) {
            return response()->json([
                "msg" => "No such id",
                "status" => 205,
                "data" => null
            ]);
        }

        $employee->update([
            "id"       => $request->id,
            "name"     => $request->name,
            "email"    => $request->email,
            "password" => Hash::make($request->password),
            "salary"   => $request->salary,
        ]);

        return response()->json([
            "msg" => "Updated Successfully",
            "status" => 200,
            "data" => new EmployeeResource($employee)
        ]);
    }
}
