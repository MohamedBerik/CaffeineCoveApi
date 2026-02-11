<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;


class SupplierController extends Controller
{
    function index()
    {
        $supplier = SupplierResource::collection(Supplier::all());
        $data = [
            "msg" => "Return All Data From supplier Table",
            "status" => 200,
            "data" => $supplier
        ];
        return response()->json($data);
    }

    function show($id)
    {
        $supplier = Supplier::find($id);

        if ($supplier) {
            $data = [
                "msg" => "Return One Record of Supplier Table",
                "status" => 200,
                "data" => new SupplierResource($supplier)
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
        $supplier = Supplier::find($id);
        if ($supplier) {

            $supplier->delete();
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
            'id' => 'required|unique:suppliers|max:20',
            'name' => 'required|min:3|max:255',
            'email' => 'required|min:3|max:255',
            'phone' => 'required|min:3|max:255',
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }

        $supplier = Supplier::create([
            "id" => $request->id,
            "name" => $request->name,
            "email" => $request->email,
            "phone" => $request->phone,
        ]);
        $data = [
            "msg" => "Created Successfully",
            "status" => 200,
            "data" => new SupplierResource($supplier)
        ];
        return response()->json($data);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $supplier = Supplier::find($old_id);

        $validate = Validator::make($request->all(), [
            "id" => ['required', Rule::unique('suppliers')->ignore($old_id)],
            "name" => "required|min:3|max:255",
            "email" => "required|min:3|max:255",
            "phone" => "required|min:3|max:255",
        ]);

        if ($validate->fails()) {
            $data = [
                "msg" => "Validation required",
                "status" => 201,
                "data" => $validate->errors()
            ];
            return response()->json($data);
        }


        if ($supplier) {

            $supplier->update([
                "id" => $request->id,
                "name" => $request->name,
                "email" => $request->email,
                "phone" => $request->phone,
            ]);
            $data = [
                "msg" => "Updated Successfully",
                "status" => 200,
                "data" => new SupplierResource($supplier)
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
