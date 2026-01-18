<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;


class CustomerController extends Controller
{
    function index()
    {
        $customer = CustomerResource::collection(Customer::all());
        $data = [
            "msg" => "Return All Data From Customer Table",
            "status" => 200,
            "data" => $customer
        ];
        return response()->json($data);
    }

    function show($id)
    {
        $customer = Customer::find($id);

        if ($customer) {
            $data = [
                "msg" => "Return One Record of Customer Table",
                "status" => 200,
                "data" => new CustomerResource($customer)
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
        $customer = Customer::find($id);
        if ($customer) {

            $customer->delete();
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
            'id' => 'required|unique:customers|max:20',
            'name' => 'required|min:3|max:255',
            'email' => 'required|min:3|max:255',
            'password' => 'required|min:3|max:255',
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }

        $customer = Customer::create([
            "id" => $request->id,
            "name" => $request->name,
            "email" => $request->email,
            "password" => Hash::make($request->password),
        ]);
        $data = [
            "msg" => "Created Successfully",
            "status" => 200,
            "data" => new CustomerResource($customer)
        ];
        return response()->json($data);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $customer = Customer::find($old_id);

        $validate = Validator::make($request->all(), [
            "id" => ['required', Rule::unique('customers')->ignore($old_id)],
            "name" => "required|min:3|max:255",
            "email" => "required|min:3|max:255",
            "password" => "required|min:3|max:255",
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }


        if ($customer) {

            $customer->update([
                "id" => $request->id,
                "name" => $request->name,
                "email" => $request->email,
                "password" => Hash::make($request->password),
            ]);
            $data = [
                "msg" => "Updated Successfully",
                "status" => 200,
                "data" => new CustomerResource($customer)
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
