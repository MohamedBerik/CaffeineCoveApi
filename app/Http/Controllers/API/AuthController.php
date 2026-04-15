<?php
// app/Http/Controllers/API/AuthController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user (for SaaS - creates company)
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:8',
            'clinic_name' => 'required|string|max:255', // ✅ اسم العيادة بدل company_id
        ]);

        // ✅ إنشاء الشركة أولاً
        $company = Company::create([
            'name' => $request->clinic_name,
            'slug' => Str::slug($request->clinic_name) . '-' . uniqid(),
            'status' => Company::STATUS_TRIAL,
            'trial_ends_at' => now()->addDays(14),
        ]);

        // ✅ إنشاء المستخدم وربطه بالشركة
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'company_id' => $company->id,
            'role' => 'admin', // أول مستخدم Admin
            'is_super_admin' => false,
        ]);

        // ✅ تعيين Tenant Context
        Tenant::setId($company->id);
        Tenant::setIsSuperAdmin(false);

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'company' => $company,
            'token' => $token
        ], 201);
    }

    /**
     * Login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // ✅ التحقق من حالة المستخدم والشركة
        if (!$user->is_super_admin && !$user->company_id) {
            return response()->json([
                'message' => 'User is not assigned to any company'
            ], 403);
        }

        // ✅ التحقق من حالة الشركة
        if (!$user->is_super_admin && $user->company) {
            if ($user->company->status === 'suspended') {
                return response()->json([
                    'message' => 'Your clinic account has been suspended. Please contact support.'
                ], 403);
            }
        }

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role', 'is_super_admin']),
            'company_id' => $user->company_id,
            'token' => $token
        ]);
    }
}
