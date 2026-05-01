<?php
// app/Http/Controllers/API/AuthController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\Concerns\CompanyScope;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Mail\WelcomeMail;
use Illuminate\Support\Facades\Mail;

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
            'clinic_name' => 'required|string|max:255',
        ]);

        // ✅ إنشاء الشركة أولاً - في Super Admin Context
        $company = Tenant::asSuperAdmin(function () use ($request) {
            return Company::create([
                'name' => $request->clinic_name,
                'slug' => Str::slug($request->clinic_name) . '-' . uniqid(),
                'status' => Company::STATUS_TRIAL,
                'trial_ends_at' => now()->addDays(14),
            ]);
        });

        // ✅ إنشاء المستخدم وربطه بالشركة
        $user = Tenant::asSuperAdmin(function () use ($request, $company) {
            return User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'company_id' => $company->id,
                'role' => 'admin',
                'is_super_admin' => false,
            ]);
        });

        // إسناد الدور تلقائيًا
        if ($user->role === 'admin') {
            $user->assignRole('admin');
        } elseif ($user->role === 'doctor') {
            $user->assignRole('doctor');
        } elseif ($user->role === 'receptionist') {
            $user->assignRole('receptionist');
        }

        Mail::to($user->email)->queue(new WelcomeMail($user));

        // ✅ تعيين Tenant Context للمستخدم الجديد
        Tenant::setId($company->id);
        Tenant::setIsSuperAdmin(false);

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role', 'is_super_admin']),
            'company' => $company->only(['id', 'name', 'slug', 'status', 'trial_ends_at']),
            'token' => $token
        ], 201);
    }

    /**
     * Login
     */
    public function login(Request $request)
    {
        Log::info('Authorization Header:', ['header' => $request->header('Authorization')]);

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // ✅ تجاوز الـ Global Scope عشان نقدر ندور على المستخدم
        $user = User::withoutGlobalScope(CompanyScope::class)
            ->where('email', $request->email)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            // ✅ Audit Logging
            event(new \App\Events\FailedLogin(
                $request->email,
                $request->ip(),
                'Invalid credentials'
            ));
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // ✅ بعد ما لاقينا المستخدم، نضبط Tenant Context
        if (!$user->is_super_admin) {
            Tenant::setId($user->company_id);
            Tenant::setIsSuperAdmin(false);
        } else {
            Tenant::setId(null);
            Tenant::setIsSuperAdmin(true);
        }

        // ✅ التحقق من حالة الشركة
        if (!$user->is_super_admin && $user->company) {
            if (in_array($user->company->status, ['suspended', 'cancelled'])) {
                return response()->json([
                    'message' => 'Your clinic account has been ' . $user->company->status
                ], 403);
            }
        }

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role', 'is_super_admin', 'branch_id']),
            'company_id' => $user->company_id,
            'company_status' => $user->company?->status,
            'token' => $token
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        // ✅ Reset Tenant Context
        Tenant::reset();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role', 'is_super_admin', 'branch_id']),
            'company_id' => Tenant::id(),
            'company' => $user->is_super_admin ? null : $user->company?->only(['id', 'name', 'slug', 'status']),
            'permissions' => $this->getUserPermissions($user),
        ]);
    }

    /**
     * Get user permissions based on role
     */
    private function getUserPermissions(User $user): array
    {
        if ($user->is_super_admin) {
            return ['*']; // كل الصلاحيات
        }

        if ($user->role === 'admin') {
            return [
                'finance.view',
                'finance.create',
                'orders.view',
                'orders.manage',
                'appointments.view',
                'appointments.manage',
                'patients.view',
                'patients.manage',
                'treatment_plans.view',
                'treatment_plans.manage',
                'procedures.view',
                'procedures.manage',
                'reports.view',
            ];
        }

        // مستخدم عادي
        return [
            'appointments.view',
            'patients.view',
        ];
    }
}
