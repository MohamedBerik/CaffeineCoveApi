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

        $user = Tenant::asSuperAdmin(function () use ($request) {
            return User::withoutGlobalScopes()
                ->where('email', $request->email)
                ->first();
        });

        if (!$user || !Hash::check($request->password, $user->password)) {
            event(new \App\Events\FailedLogin(
                $request->email,
                $request->ip(),
                'Invalid credentials'
            ));
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // ✅ ضبط Tenant Context
        if (!$user->is_super_admin) {
            Tenant::setId($user->company_id);
            Tenant::setIsSuperAdmin(false);
        } else {
            Tenant::setId(null);
            Tenant::setIsSuperAdmin(true);
        }

        // ✅ فحص حالة الاشتراك (والسماح بتسجيل الدخول مع علامة)
        $requiresSubscription = false;
        $subscriptionMessage = '';

        if (!$user->is_super_admin && $user->company) {
            $status = $user->company->status;
            $trialEnd = $user->company->trial_ends_at;

            if (in_array($status, ['suspended', 'cancelled'])) {
                $requiresSubscription = true;
                $subscriptionMessage = $status === 'suspended'
                    ? 'Your clinic account has been suspended. Please subscribe to reactivate.'
                    : 'Your clinic account has been cancelled.';
            } elseif ($status === 'trial' && $trialEnd && now()->gt($trialEnd)) {
                $requiresSubscription = true;
                $subscriptionMessage = 'Your free trial has ended. Please subscribe to continue using the system.';
            }
        }

        $token = $user->createToken('API Token')->plainTextToken;

        $response = [
            'user' => $user->only(['id', 'name', 'email', 'role', 'is_super_admin', 'branch_id']),
            'company_id' => $user->company_id,
            'company_status' => $user->company?->status,
            'token' => $token,
        ];

        // ✅ إضافة علامة الاشتراك إذا لزم الأمر
        if ($requiresSubscription) {
            $response['requires_subscription'] = true;
            $response['message'] = $subscriptionMessage;
            $response['redirect_to'] = '/admin/erp/billing';
        }

        return response()->json($response);
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
        try {
            $user = $request->user();

            // جلب الصلاحيات بشكل آمن ومتوافق مع حزم Spatie أو الصلاحيات المخصصة لديك
            $permissions = [];
            if (method_exists($user, 'getAllPermissions')) {
                $permissions = $user->getAllPermissions()->pluck('name')->toArray();
            } elseif (isset($user->permissions)) {
                $permissions = $user->permissions;
            }

            if ($user->is_super_admin) {
                $permissions = ['*'];
            }

            $permissionsMap = array_fill_keys($permissions, true);

            // تأمين جلب الشركة لعدم حدوث خطأ 500 إذا كانت العلاقة فارغة
            $companyData = null;
            if (!$user->is_super_admin && method_exists($user, 'company') && $user->company) {
                $companyData = $user->company->only(['id', 'name', 'slug', 'status']);
            }

            // إرجاع الرد بالهيكل الشامل الموحد الذي يحتاجه الـ Front-end (بما فيها السوكيت والداشبورد)
            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_super_admin' => (bool) $user->is_super_admin,
                'branch_id'  => $user->branch_id,
                'company_id' => Tenant::id() ?? $user->company_id ?? null,
                'company' => $companyData,
                'can_switch_branch' => !$user->is_super_admin && $user->role === 'admin',
                'permissions' => $permissions,
                'permissions_map' => $permissionsMap,
            ]);
        } catch (\Exception $e) {
            // منع السيرفر من الانهيار وإرجاع رسالة خطأ واضحة بدلاً من 500 صامتة
            return response()->json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage()
            ], 500);
        }
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
