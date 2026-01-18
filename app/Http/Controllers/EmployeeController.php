<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;


class EmployeeController extends Controller
{
    public function index()
    {
        $employee = Employee::all();
        return view('employee.index', ["result" => $employee]);
    }

    public function show($id)
    {
        $employee = Employee::findOrFail($id);
        return view("Employee.show", ["result" => $employee]);
    }

    public function delete($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();
        return redirect()->route("dashboard")->with("employeemessage", "Deleted Successfully");
    }

    public function create()
    {
        return view("Employee.create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|unique:employees|max:20',
            'name' => 'required|min:3|max:255',
            'email' => 'required|min:3|max:255',
            'password' => 'required|min:3|max:255',
            'salary' => 'required|min:3|max:255',
        ]);

        Employee::create([
            "id" => $request->id,
            "name" => $request->name,
            "email" => $request->email,
            "password" => Hash::make($request->password),
            "salary" => $request->salary,
        ]);
        return redirect()->route("dashboard")->with("employeemessage", "Created Successfully");
    }

    public function edit($id)
    {
        $employee = Employee::findOrFail($id);
        return view("Employee.edit", ["result" => $employee]);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $employee = Employee::findOrFail($old_id);

        $request->validate([
            "id" => ['required', Rule::unique('employees')->ignore($old_id)],
            "name" => "required|min:3|max:255",
            "email" => "required|min:3|max:255",
            "password" => "required|min:3|max:255",
            "salary" => "required|min:3|max:255",
        ]);

        $employee->update([
            "id" => $request->id,
            "name" => $request->name,
            "email" => $request->email,
            "password" => Hash::make($request->password),
            "salary" => $request->salary,
        ]);
        return redirect()->route("dashboard")->with("employeemessage", "Updated Successfully");
    }
}
