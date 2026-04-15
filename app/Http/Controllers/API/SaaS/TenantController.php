<?php

namespace App\Http\Controllers\API\SaaS;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Concerns\CompanyScope;
use App\Services\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * Get current tenant (company) information
     * Used by frontend after login to initialize app
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'msg' => 'Unauthenticated (missing/invalid token)',
            ], 401);
        }

        // ✅ Super Admin - يمكنه رؤية أي شركة أو بدون شركة
        if ($user->is_super_admin) {
            return $this->superAdminResponse($user, $request);
        }

        // ✅ Regular User - لازم يكون مرتبط بشركة
        if (!$user->company_id) {
            return response()->json([
                'msg' => 'User is not associated with any company',
                'debug' => [
                    'user_id' => $user->id,
                    'user_company_id' => $user->company_id,
                ],
            ], 422);
        }

        // ✅ جلب الشركة مع تجاوز الـ Scope (عشان نضمن نجاح الاستعلام)
        $company = Tenant::asSuperAdmin(function () use ($user) {
            return Company::find($user->company_id);
        });

        if (!$company) {
            return response()->json([
                'msg' => 'Company not found for this user',
                'debug' => [
                    'user_id' => $user->id,
                    'user_company_id' => $user->company_id,
                ],
            ], 422);
        }

        // ✅ التحقق من حالة الشركة
        if ($company->status === 'suspended') {
            return response()->json([
                'msg' => 'Your clinic account has been suspended. Please contact support.',
                'tenant' => [
                    'company_id' => $company->id,
                    'slug' => $company->slug,
                    'status' => $company->status,
                ],
            ], 403);
        }

        // ✅ تعيين Tenant Context
        Tenant::setId($company->id);
        Tenant::setIsSuperAdmin(false);

        return response()->json([
            'tenant' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'status' => $company->status,
                'trial_ends_at' => $company->trial_ends_at,
                'trial_days_left' => $company->trial_ends_at
                    ? now()->diffInDays($company->trial_ends_at, false)
                    : null,
                'branding' => $company->branding ?? [
                    'app_name' => $company->name,
                    'logo' => null,
                    'primary_color' => '#0ea5e9',
                ],
                'created_at' => $company->created_at,
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_id' => $user->company_id,
                'role' => $user->role,
                'is_super_admin' => $user->is_super_admin,
                'permissions' => $this->getUserPermissions($user),
            ],
            'features' => $this->getCompanyFeatures($company),
        ]);
    }

    /**
     * Response for Super Admin
     */
    private function superAdminResponse($user, Request $request): \Illuminate\Http\JsonResponse
    {
        $company = null;

        // ✅ Super Admin ممكن يشوف شركة معينة لو حدد company_id
        if ($request->has('company_id')) {
            $company = Company::withoutGlobalScope(CompanyScope::class)
                ->find($request->company_id);

            if ($company) {
                Tenant::setId($company->id);
                Tenant::setIsSuperAdmin(false); // مؤقتًا في سياق الشركة
            }
        }

        return response()->json([
            'tenant' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'status' => $company->status,
                'trial_ends_at' => $company->trial_ends_at,
                'branding' => $company->branding,
                'created_at' => $company->created_at,
            ] : null,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_id' => $user->company_id,
                'role' => $user->role,
                'is_super_admin' => true,
                'permissions' => ['*'], // كل الصلاحيات
            ],
            'features' => [
                'can_switch_companies' => true,
                'can_view_all_data' => true,
                'can_manage_companies' => true,
            ],
            'all_companies' => $this->getAllCompaniesSummary(),
        ]);
    }

    /**
     * Get user permissions based on role
     */
    private function getUserPermissions($user): array
    {
        if ($user->is_super_admin) {
            return ['*'];
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
                'reports.export',
                'settings.view',
                'settings.manage',
            ];
        }

        // Regular user
        return [
            'appointments.view',
            'patients.view',
        ];
    }

    /**
     * Get features enabled for this company
     */
    private function getCompanyFeatures(Company $company): array
    {
        return [
            'max_users' => $company->status === 'trial' ? 5 : null,
            'max_patients' => null, // Unlimited
            'max_appointments_per_day' => null,
            'can_export_reports' => true,
            'can_customize_branding' => $company->status === 'active',
            'can_send_sms' => $company->status === 'active',
            'can_send_email' => true,
            'can_use_whatsapp' => $company->status === 'active',
            'trial_active' => $company->status === 'trial' && $company->trial_ends_at?->isFuture(),
        ];
    }

    /**
     * Get summary of all companies (for Super Admin)
     */
    private function getAllCompaniesSummary(): array
    {
        return Company::query()
            ->select(['id', 'name', 'slug', 'status', 'trial_ends_at', 'created_at'])
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'status' => $c->status,
                'trial_ends_at' => $c->trial_ends_at,
                'created_at' => $c->created_at,
            ])
            ->toArray();
    }

    /**
     * Switch to a different company (Super Admin only)
     */
    public function switch(Request $request)
    {
        $user = $request->user();

        if (!$user->is_super_admin) {
            return response()->json([
                'msg' => 'Only super admin can switch companies',
            ], 403);
        }

        $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
        ]);

        $company = Company::find($request->company_id);

        // ✅ تعيين Context الشركة الجديدة
        Tenant::setId($company->id);
        Tenant::setIsSuperAdmin(false);

        return response()->json([
            'msg' => 'Switched to company successfully',
            'tenant' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'status' => $company->status,
            ],
        ]);
    }

    /**
     * Exit company context and return to Super Admin mode
     */
    public function exitCompany(Request $request)
    {
        $user = $request->user();

        if (!$user->is_super_admin) {
            return response()->json([
                'msg' => 'Only super admin can exit company context',
            ], 403);
        }

        // ✅ الرجوع لـ Super Admin Context
        Tenant::setId(null);
        Tenant::setIsSuperAdmin(true);

        return response()->json([
            'msg' => 'Exited company context. Now in Super Admin mode.',
            'tenant' => null,
        ]);
    }
}
