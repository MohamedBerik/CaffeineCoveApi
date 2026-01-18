<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;


class EmployeeController extends Controller
{
    function index()
    {
        $employee = EmployeeResource::collection(Employee::all());
        $data = [
            "msg" => "Return All Data From Employee Table",
            "status" => 200,
            "data" => $employee
        ];
        return response()->json($data);
    }

    function show($id)
    {
        $employee = Employee::find($id);

        if ($employee) {
            $data = [
                "msg" => "Return One Record of Employee Table",
                "status" => 200,
                "data" => new EmployeeResource($employee)
            ];
            return response()->json($data);
        } else {
            $data = [
                "msg" => "No Such id",
                "status" => 205,
                "data" => null
            ];
            return response()->json($data);
        }
    }

    function delete(Request $request)
    {
        $id = $request->id;
        $employee = Employee::find($id);
        if ($employee) {

            $employee->delete();
            $data = [
                "msg" => "Deleted Successfully",
                "status" => 200,
                "data" => null
            ];
            return response()->json($data);
        } else {
            $data = [
                "msg" => "No Such id",
                "status" => 205,
                "data" => null
            ];
            return response()->json($data);
        }
    }

    public function store(Request $request)
    {

        $validate = Validator::make($request->all(), [
            'id' => 'required|unique:employees|max:20',
            'name' => 'required|min:3|max:255',
            'email' => 'required|min:3|max:255',
            'password' => 'required|min:3|max:255',
            'salary' => 'required',
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }

        $employee = Employee::create([
            "id" => $request->id,
            "name" => $request->name,
            "email" => $request->email,
            "password" => Hash::make($request->password),
            "salary" => $request->salary,
        ]);
        $data = [
            "msg" => "Created Successfully",
            "status" => 200,
            "data" => new EmployeeResource($employee)
        ];
        return response()->json($data);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $employee = Employee::find($old_id);

        $validate = Validator::make($request->all(), [
            "id" => ['required', Rule::unique('employees')->ignore($old_id)],
            "name" => "required|min:3|max:255",
            "email" => "required|min:3|max:255",
            "password" => "required|min:3|max:255",
            "salary" => "required",
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }


        if ($employee) {
            $employee->update([
                "id" => $request->id,
                "name" => $request->name,
                "email" => $request->email,
                "password" => Hash::make($request->password),
                "salary" => $request->salary,
            ]);
            $data = [
                "msg" => "Updated Successfully",
                "status" => 200,
                "data" => new EmployeeResource($employee)
            ];
            return response()->json($data);
        } else {
            $data = [
                "msg" => "No such id",
                "status" => 205,
                "data" => null
            ];
            return response()->json($data);
        }
    }
}
