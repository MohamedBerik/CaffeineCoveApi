<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;


class CustomerController extends Controller
{
    public function index()
    {
        $customer = Customer::all();
        return view('customer.index', ["result" => $customer]);
    }

    public function show($id)
    {
        $customer = Customer::findOrFail($id);
        return view("Customer.show", ["result" => $customer]);
    }

    public function delete($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();
        return redirect()->route("dashboard")->with("customermessage", "Deleted Successfully");
    }

    public function create()
    {
        return view("Customer.create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|unique:customers|max:20',
            'name' => 'required|min:3|max:255',
            'email' => 'required|min:3|max:255',
            'password' => 'required|min:3|max:255',
        ]);

        Customer::create([
            "id" => $request->id,
            "name" => $request->name,
            "email" => $request->email,
            "password" => Hash::make($request->password),
        ]);
        return redirect()->route("dashboard")->with("customermessage", "Created Successfully");
    }

    public function edit($id)
    {
        $customer = Customer::findOrFail($id);
        return view("Customer.edit", ["result" => $customer]);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $customer = Customer::findOrFail($old_id);

        $request->validate([
            "id" => ['required', Rule::unique('customers')->ignore($old_id)],
            "name" => "required|min:3|max:255",
            "email" => "required|min:3|max:255",
            "password" => "required|min:3|max:255",
        ]);

        $customer->update([
            "id" => $request->id,
            "name" => $request->name,
            "email" => $request->email,
            "password" => Hash::make($request->password),
        ]);
        return redirect()->route("dashboard")->with("customermessage", "Updated Successfully");
    }
}
