<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Concerns\CompanyScope;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        // ✅ Super Admin يشوف كل المستخدمين
        if (Tenant::isSuperAdmin() && $request->filled('company_id')) {
            $query = User::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $request->company_id);
        }

        $users = UserResource::collection($query->get());

        return response()->json([
            "msg" => "Users list",
            "status" => 200,
            "data" => $users
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = User::query()->find($id);

        if ($user) {
            return response()->json([
                "msg" => "Return One Record of User Table",
                "status" => 200,
                "data" => new UserResource($user)
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
        $user = User::query()->find($request->id);

        if (!$user) {
            return response()->json([
                "msg" => "No Such id",
                "status" => 404,
                "data" => null
            ], 404);
        }

        // ✅ منع حذف Super Admin
        if ($user->is_super_admin) {
            return response()->json([
                "msg" => "Cannot delete super admin user",
                "status" => 403,
                "data" => null
            ], 403);
        }

        // ✅ منع المستخدم من حذف نفسه
        if ($user->id === $request->user()->id) {
            return response()->json([
                "msg" => "Cannot delete your own account",
                "status" => 403,
                "data" => null
            ], 403);
        }

        $user->delete();

        return response()->json([
            "msg" => "Deleted Successfully",
            "status" => 200,
            "data" => null
        ]);
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required|min:3|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|max:255',
            'role' => ['nullable', Rule::in(['admin', 'user'])],
        ]);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 422,
                "data" => $validate->errors()
            ], 422);
        }

        // ✅ تحديد company_id
        $companyId = Tenant::id();

        // ✅ Super Admin ممكن يحدد company_id
        if (Tenant::isSuperAdmin() && $request->filled('company_id')) {
            $companyId = $request->company_id;
        }

        if (!$companyId) {
            return response()->json([
                "msg" => "Company ID is required",
                "status" => 422,
                "data" => null
            ], 422);
        }

        $user = User::create([
            "name" => $request->name,
            "email" => $request->email,
            "password" => Hash::make($request->password),
            "company_id" => $companyId,
            "role" => $request->role ?? 'user',
            "is_super_admin" => false,
        ]);

        return response()->json([
            "msg" => "Created Successfully",
            "status" => 201,
            "data" => new UserResource($user)
        ], 201);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $user = User::query()->find($old_id);

        if (!$user) {
            return response()->json([
                "msg" => "No such id",
                "status" => 404,
                "data" => null
            ], 404);
        }

        // ✅ منع تعديل Super Admin بواسطة Company Admin
        if ($user->is_super_admin && !Tenant::isSuperAdmin()) {
            return response()->json([
                "msg" => "Cannot modify super admin user",
                "status" => 403,
                "data" => null
            ], 403);
        }

        $rules = [
            "name" => "required|min:3|max:255",
            "email" => "required|email|unique:users,email," . $old_id,
            "role" => ['nullable', Rule::in(['admin', 'user'])],
        ];

        // Password optional in update
        if ($request->filled('password')) {
            $rules['password'] = 'min:6|max:255';
        }

        $validate = Validator::make($request->all(), $rules);

        if ($validate->fails()) {
            return response()->json([
                "msg" => "Validation required",
                "status" => 422,
                "data" => $validate->errors()
            ], 422);
        }

        $updateData = [
            "name" => $request->name,
            "email" => $request->email,
        ];

        if ($request->filled('password')) {
            $updateData["password"] = Hash::make($request->password);
        }

        // ✅ Company Admin يعدل role فقط
        if ($request->filled('role')) {
            $updateData["role"] = $request->role;
        }

        $user->update($updateData);

        return response()->json([
            "msg" => "Updated Successfully",
            "status" => 200,
            "data" => new UserResource($user->fresh())
        ]);
    }
}
