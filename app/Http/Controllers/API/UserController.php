<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;


class UserController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $users = UserResource::collection(
            User::where('company_id', $companyId)->get()
        );

        return response()->json([
            "msg" => "Users list",
            "status" => 200,
            "data" => $users
        ]);
    }


    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $user = User::where('company_id', $companyId)->find($id);

        if ($user) {
            $data = [
                "msg" => "Return One Record of User Table",
                "status" => 200,
                "data" => new UserResource($user)
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

    public function delete(Request $request)
    {
        $companyId = $request->user()->company_id;

        $user = User::where('company_id', $companyId)
            ->find($request->id);

        if ($user) {

            $user->delete();
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
            'id' => 'required|unique:users|max:20',
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

        $user = User::create([
            "id" => $request->id,
            "name" => $request->name,
            "email" => $request->email,
            "password" => Hash::make($request->password),
            "company_id" => $request->user()->company_id
        ]);
        $data = [
            "msg" => "Created Successfully",
            "status" => 200,
            "data" => new UserResource($user)
        ];
        return response()->json($data);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $companyId = $request->user()->company_id;

        $user = User::where('company_id', $companyId)->find($old_id);

        $validate = Validator::make($request->all(), [
            "id" => ['required', Rule::unique('users')->ignore($old_id)],
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


        if ($user) {
            $user->update([
                "id" => $request->id,
                "name" => $request->name,
                "email" => $request->email,
                "password" => Hash::make($request->password),
            ]);
            $data = [
                "msg" => "Updated Successfully",
                "status" => 200,
                "data" => new UserResource($user)
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
