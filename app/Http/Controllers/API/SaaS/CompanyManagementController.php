<?php

namespace App\Http\Controllers\API\SaaS;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class CompanyManagementController extends Controller
{
    /**
     * GET /api/admin/companies
     * عرض كل الشركات (مع فلترة وبحث وترتيب)
     */
    public function index(Request $request)
    {
        $query = Company::query()
            ->withCount('users')
            ->withCount(['invoices as total_revenue' => function ($q) {
                $q->select(DB::raw('SUM(total)'));
            }]);

        // بحث
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // فلترة حسب الحالة
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // ترتيب
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $companies = $query->paginate($perPage);

        return response()->json([
            'msg' => 'Companies list',
            'status' => 200,
            'data' => $companies->items(),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
            ],
        ]);
    }

    /**
     * POST /api/admin/companies
     * إنشاء شركة جديدة
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:companies,slug'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'trial', 'suspended', 'cancelled'])],
            'trial_ends_at' => ['nullable', 'date'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        return DB::transaction(function () use ($data) {
            // إنشاء الشركة
            $company = Company::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'contact_person' => $data['contact_person'] ?? null,
                'status' => $data['status'],
                'trial_ends_at' => $data['trial_ends_at'] ?? null,
            ]);

            // إنشاء مدير الشركة
            User::create([
                'company_id' => $company->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => bcrypt($data['admin_password']),
                'role' => 'admin',
                'is_super_admin' => false,
                'status' => 1,
            ]);

            return response()->json([
                'msg' => 'Company created successfully',
                'status' => 201,
                'data' => $company,
            ], 201);
        });
    }

    /**
     * GET /api/admin/companies/{id}
     * عرض تفاصيل شركة
     */
    public function show($id)
    {
        $company = Company::withCount('users')
            ->withCount(['invoices as total_revenue' => function ($q) {
                $q->select(DB::raw('SUM(total)'));
            }])
            ->findOrFail($id);

        return response()->json([
            'msg' => 'Company details',
            'status' => 200,
            'data' => $company,
        ]);
    }

    /**
     * PUT /api/admin/companies/{id}
     * تحديث شركة
     */
    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'trial', 'suspended', 'cancelled'])],
            'trial_ends_at' => ['nullable', 'date'],
        ]);

        $company->update($data);

        return response()->json([
            'msg' => 'Company updated successfully',
            'status' => 200,
            'data' => $company,
        ]);
    }

    /**
     * DELETE /api/admin/companies/{id}
     * حذف شركة
     */
    public function destroy($id)
    {
        $company = Company::findOrFail($id);

        // حذف كل المستخدمين المرتبطين
        User::where('company_id', $company->id)->delete();

        $company->delete();

        return response()->json([
            'msg' => 'Company deleted successfully',
            'status' => 200,
        ]);
    }

    /**
     * POST /api/admin/companies/{id}/suspend
     * تعليق شركة
     */
    public function suspend($id)
    {
        $company = Company::findOrFail($id);
        $company->update(['status' => 'suspended']);

        return response()->json([
            'msg' => 'Company suspended successfully',
            'status' => 200,
        ]);
    }

    /**
     * POST /api/admin/companies/{id}/activate
     * تفعيل شركة
     */
    public function activate($id)
    {
        $company = Company::findOrFail($id);
        $company->update(['status' => 'active']);

        return response()->json([
            'msg' => 'Company activated successfully',
            'status' => 200,
        ]);
    }

    /**
     * GET /api/admin/companies/{id}/stats
     * إحصائيات الشركة
     */
    public function stats($id)
    {
        $company = Company::findOrFail($id);

        $stats = [
            'total_users' => User::where('company_id', $id)->count(),
            'total_patients' => \App\Models\Customer::where('company_id', $id)->count(),
            'total_appointments' => \App\Models\Appointment::where('company_id', $id)->count(),
            'total_revenue' => (float) \App\Models\Payment::where('company_id', $id)->sum('applied_amount'),
            'mrr' => (float) \App\Models\Invoice::where('company_id', $id)
                ->whereMonth('issued_at', now()->month)
                ->sum('total'),
            'outstanding' => (float) \App\Models\Invoice::where('company_id', $id)
                ->whereIn('status', ['unpaid', 'partially_paid'])
                ->sum('total'),
            'appointments_this_month' => \App\Models\Appointment::where('company_id', $id)
                ->whereMonth('appointment_date', now()->month)
                ->count(),
            'revenue_this_month' => (float) \App\Models\Payment::where('company_id', $id)
                ->whereMonth('paid_at', now()->month)
                ->sum('applied_amount'),
            'new_patients_this_month' => \App\Models\Customer::where('company_id', $id)
                ->whereMonth('created_at', now()->month)
                ->count(),
        ];

        return response()->json([
            'msg' => 'Company statistics',
            'status' => 200,
            'data' => $stats,
        ]);
    }

    /**
     * GET /api/admin/companies/{id}/users
     * مستخدمي الشركة
     */
    public function users($id)
    {
        $users = User::where('company_id', $id)
            ->select('id', 'name', 'email', 'role', 'status')
            ->get();

        return response()->json([
            'msg' => 'Company users',
            'status' => 200,
            'data' => $users,
        ]);
    }

    /**
     * GET /api/admin/companies/{id}/subscriptions
     * اشتراكات الشركة
     */
    public function subscriptions($id)
    {
        $subscriptions = Subscription::where('company_id', $id)
            ->with('plan')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'msg' => 'Company subscriptions',
            'status' => 200,
            'data' => $subscriptions,
        ]);
    }

    /**
     * POST /api/saas/companies/{id}/force-cancel-subscription
     * إلغاء اشتراك شركة بالقوة (Admin Override)
     */
    public function forceCancelSubscription($id)
    {
        $subscription = Subscription::where('company_id', $id)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return response()->json(['msg' => 'No active subscription found'], 404);
        }

        $subscription->update(['status' => 'cancelled']);

        // ✅ Audit Logging
        event(new \App\Events\AdminOverride(
            auth()->id(),
            'force_cancel_subscription',
            $subscription->id,
            ['company_id' => $id, 'plan_id' => $subscription->plan_id]
        ));

        event(new \App\Events\SuspiciousActivity(
            auth()->id(),
            'admin_force_action',
            ['action' => 'cancel_subscription', 'subscription_id' => $subscription->id]
        ));

        return response()->json([
            'msg' => 'Subscription force cancelled successfully',
            'status' => 200,
        ]);
    }

    /**
     * POST /api/saas/companies/{id}/adjust-billing
     * تعديل الفوترة يدويًا (Admin Override)
     */
    public function adjustBilling(Request $request, $id)
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $subscription = Subscription::where('company_id', $id)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return response()->json(['msg' => 'No active subscription found'], 404);
        }

        $oldAmount = $subscription->amount;
        $newAmount = $request->amount;

        $subscription->update(['amount' => $newAmount]);

        // ✅ إنشاء فاتورة تعديل
        $invoice = BillingInvoice::create([
            'company_id' => $id,
            'subscription_id' => $subscription->id,
            'number' => BillingInvoice::generateNumber(),
            'amount' => $newAmount - $oldAmount,
            'tax' => 0,
            'total' => $newAmount - $oldAmount,
            'status' => 'paid',
            'paid_at' => now(),
            'transaction_id' => 'manual_adjustment',
        ]);

        // ✅ Audit Logging
        event(new \App\Events\AdminOverride(
            auth()->id(),
            'adjust_billing',
            $subscription->id,
            [
                'company_id' => $id,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
                'reason' => $request->reason,
            ]
        ));

        return response()->json([
            'msg' => 'Billing adjusted successfully',
            'status' => 200,
            'old_amount' => $oldAmount,
            'new_amount' => $newAmount,
            'invoice' => $invoice,
        ]);
    }

    /**
     * POST /api/saas/companies/{id}/impersonate
     * تقمص مستخدم داخل شركة (Admin Override)
     */
    public function impersonate(Request $request, $id)
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = User::where('company_id', $id)
            ->where('id', $request->user_id)
            ->first();

        if (!$user) {
            return response()->json(['msg' => 'User not found in this company'], 404);
        }

        // ✅ إنشاء Token للمستخدم المتقمص
        $token = $user->createToken('impersonation')->plainTextToken;

        // ✅ Audit Logging
        event(new \App\Events\AdminOverride(
            auth()->id(),
            'impersonate_user',
            $user->id,
            ['company_id' => $id, 'impersonated_user_id' => $user->id]
        ));

        return response()->json([
            'msg' => 'Impersonation token generated',
            'status' => 200,
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'company_id' => $id,
        ]);
    }

    public function exportClinic($id)
    {
        Artisan::call('clinic:export', ['company_id' => $id]);

        $files = File::glob(storage_path("app/clinic_{$id}_export_*.zip"));
        if (empty($files)) {
            return response()->json(['msg' => 'Export failed or no file found'], 500);
        }

        rsort($files);
        $latestFile = basename($files[0]);

        return response()->json([
            'msg' => 'Export ready',
            'download_url' => "/saas/companies/{$id}/export-download?file={$latestFile}"
        ]);
    }

    public function downloadExport($id, Request $request)
    {
        $fileName = $request->query('file');
        $filePath = storage_path("app/{$fileName}");

        if (!File::exists($filePath)) {
            return response()->json(['msg' => 'File not found'], 404);
        }

        return response()->download($filePath, $fileName);
    }
}
