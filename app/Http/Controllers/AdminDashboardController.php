<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Concerns\CompanyScope;
use App\Services\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Reservation;
use App\Models\Supplier;

class AdminDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // ✅ التحقق من صلاحية الوصول للـ Admin Dashboard
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Regular users cannot access admin dashboard
        if (!$user->is_super_admin && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        // ✅ Super Admin - يشوف كل الشركات
        if (Tenant::isSuperAdmin()) {
            return $this->superAdminDashboard($request);
        }

        // ✅ Company Admin - يشوف شركته فقط
        return $this->companyAdminDashboard($request);
    }

    /**
     * Dashboard for Company Admin
     */
    private function companyAdminDashboard(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 200,
            'type' => 'company',

            /* ========= COUNTS ========= */
            'statistics' => [
                'users'        => User::query()->count(),
                'categories'   => Category::query()->count(),
                'products'     => Product::query()->count(),
                'customers'    => Customer::query()->count(),
                'orders'       => Order::query()->count(),
                'employees'    => Employee::query()->count(),
                'sales'        => Sale::query()->count(),
                'reservations' => Reservation::query()->count(),
                'invoices'     => Invoice::query()->count(),
                'suppliers'    => Supplier::query()->count(),
            ],

            /* ========= LATEST DATA ========= */
            'latest' => [
                'users'        => User::query()->latest()->take(5)->get(),
                'categories'   => Category::query()->latest()->take(5)->get(),
                'products'     => Product::query()->latest()->take(5)->get(),
                'customers'    => Customer::query()->latest()->take(5)->get(),
                'orders'       => Order::query()->latest()->take(5)->get(),
                'employees'    => Employee::query()->latest()->take(5)->get(),
                'sales'        => Sale::query()->latest()->take(5)->get(),
                'reservations' => Reservation::query()->latest()->take(5)->get(),
                'invoices'     => Invoice::query()->latest()->take(5)->get(),
                'suppliers'    => Supplier::query()->latest()->take(5)->get(),
            ],

            'company' => [
                'id' => Tenant::id(),
                'name' => optional(auth()->user()->company)->name,
            ],
        ]);
    }

    /**
     * Dashboard for Super Admin - shows all companies data
     */
    private function superAdminDashboard(Request $request): JsonResponse
    {
        // ✅ فلترة حسب company_id لو Super Admin عايز يشوف شركة معينة
        $filterCompanyId = $request->query('company_id');

        return response()->json([
            'status' => 200,
            'type' => 'super_admin',
            'filter_company_id' => $filterCompanyId,

            /* ========= COUNTS ========= */
            'statistics' => [
                'users'        => $this->countWithFilter(User::class, $filterCompanyId),
                'categories'   => $this->countWithFilter(Category::class, $filterCompanyId),
                'products'     => $this->countWithFilter(Product::class, $filterCompanyId),
                'customers'    => $this->countWithFilter(Customer::class, $filterCompanyId),
                'orders'       => $this->countWithFilter(Order::class, $filterCompanyId),
                'employees'    => $this->countWithFilter(Employee::class, $filterCompanyId),
                'sales'        => $this->countWithFilter(Sale::class, $filterCompanyId),
                'reservations' => $this->countWithFilter(Reservation::class, $filterCompanyId),
                'invoices'     => $this->countWithFilter(Invoice::class, $filterCompanyId),
                'suppliers'    => $this->countWithFilter(Supplier::class, $filterCompanyId),
            ],

            /* ========= LATEST DATA ========= */
            'latest' => [
                'users'        => $this->latestWithFilter(User::class, $filterCompanyId),
                'categories'   => $this->latestWithFilter(Category::class, $filterCompanyId),
                'products'     => $this->latestWithFilter(Product::class, $filterCompanyId),
                'customers'    => $this->latestWithFilter(Customer::class, $filterCompanyId),
                'orders'       => $this->latestWithFilter(Order::class, $filterCompanyId),
                'employees'    => $this->latestWithFilter(Employee::class, $filterCompanyId),
                'sales'        => $this->latestWithFilter(Sale::class, $filterCompanyId),
                'reservations' => $this->latestWithFilter(Reservation::class, $filterCompanyId),
                'invoices'     => $this->latestWithFilter(Invoice::class, $filterCompanyId),
                'suppliers'    => $this->latestWithFilter(Supplier::class, $filterCompanyId),
            ],

            /* ========= ALL COMPANIES SUMMARY ========= */
            'companies_summary' => $this->getCompaniesSummary(),
        ]);
    }

    /**
     * Count records with optional company filter
     */
    private function countWithFilter(string $model, ?int $companyId): int
    {
        $query = $model::withoutGlobalScope(CompanyScope::class);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->count();
    }

    /**
     * Get latest records with optional company filter
     */
    private function latestWithFilter(string $model, ?int $companyId)
    {
        $query = $model::withoutGlobalScope(CompanyScope::class);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->latest()->take(5)->get();
    }

    /**
     * Get summary of all companies
     */
    private function getCompaniesSummary(): array
    {
        return \App\Models\Company::query()
            ->withCount(['users', 'customers', 'invoices', 'appointments'])
            ->orderBy('name')
            ->get()
            ->map(fn($company) => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'status' => $company->status,
                'users_count' => $company->users_count,
                'customers_count' => $company->customers_count,
                'invoices_count' => $company->invoices_count,
                'appointments_count' => $company->appointments_count,
                'trial_ends_at' => $company->trial_ends_at,
                'created_at' => $company->created_at,
            ])
            ->toArray();
    }
}
