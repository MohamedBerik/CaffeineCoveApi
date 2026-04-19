<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\DentalRecord;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PatientRadiology;
use App\Models\Payment;
use App\Models\Procedure;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SystemAlert;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Policies\ActivityLogPolicy;
use App\Policies\AppointmentPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DentalRecordPolicy;
use App\Policies\DoctorPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PatientRadiologyPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\SystemAlertPolicy;
use App\Policies\TreatmentPlanPolicy;
use App\Services\Tenant;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Appointment::class => AppointmentPolicy::class,
        Invoice::class => InvoicePolicy::class, // ✅ أضف السطر ده
        Customer::class => CustomerPolicy::class, // ✅ أضف السطر ده
        SystemAlert::class => SystemAlertPolicy::class, // ✅ أضف السطر ده
        ActivityLog::class => ActivityLogPolicy::class, // ✅ أضف السطر ده
        DentalRecord::class => DentalRecordPolicy::class, // ✅ أضف السطر ده
        Doctor::class => DoctorPolicy::class, // ✅ أضف السطر ده
        \App\Models\Order::class => \App\Policies\OrderPolicy::class,
        \App\Models\Procedure::class => \App\Policies\ProcedurePolicy::class,
        \App\Models\PurchaseOrder::class => \App\Policies\PurchaseOrderPolicy::class,
        PatientRadiology::class => PatientRadiologyPolicy::class,
        Supplier::class => SupplierPolicy::class,
        TreatmentPlan::class => TreatmentPlanPolicy::class,


    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // ✅ Register global Gates
        $this->registerGlobalGates();

        // ✅ Register tenant-aware Gates
        $this->registerTenantGates();

        // ✅ Register Super Admin Gates
        $this->registerSuperAdminGates();
    }

    /**
     * Register global Gates (apply to all users)
     */
    protected function registerGlobalGates(): void
    {
        // ✅ Super Admin bypass all
        Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
            return null; // Continue to other gates
        });
    }

    /**
     * Register tenant-aware Gates (company-specific)
     */
    protected function registerTenantGates(): void
    {
        // ✅ Check if user belongs to the same company as the resource
        Gate::define('access-company-resource', function (User $user, $resource) {
            if (!$resource) {
                return false;
            }

            // If resource has company_id, check it
            if (isset($resource->company_id)) {
                return $user->company_id === $resource->company_id;
            }

            return true;
        });

        // ✅ View any resource within user's company
        Gate::define('view-company-data', function (User $user, string $model) {
            return !$user->isSuperAdmin() && $user->company_id !== null;
        });
    }

    /**
     * Register Super Admin specific Gates
     */
    protected function registerSuperAdminGates(): void
    {
        // ✅ Manage companies
        Gate::define('manage-companies', function (User $user) {
            return $user->isSuperAdmin();
        });

        // ✅ View all companies data
        Gate::define('view-all-companies', function (User $user) {
            return $user->isSuperAdmin();
        });

        // ✅ Switch between companies
        Gate::define('switch-company', function (User $user) {
            return $user->isSuperAdmin();
        });

        // ✅ Access admin panel
        Gate::define('access-admin-panel', function (User $user) {
            return $user->isSuperAdmin() || $user->role === 'admin';
        });

        // ✅ Manage users (Super Admin or Company Admin)
        Gate::define('manage-users', function (User $user, ?User $targetUser = null) {
            // Super admin can manage any user
            if ($user->isSuperAdmin()) {
                return true;
            }

            // Company admin can manage users in their company
            if ($user->role === 'admin' && $targetUser) {
                return $user->company_id === $targetUser->company_id;
            }

            return false;
        });
    }

    /**
     * Register model-specific Gates
     */
    protected function registerModelGates(): void
    {
        // Appointments
        Gate::define('view-appointment', function (User $user, Appointment $appointment) {
            return $user->isSuperAdmin() || $user->company_id === $appointment->company_id;
        });

        Gate::define('manage-appointment', function (User $user, Appointment $appointment) {
            return $user->isSuperAdmin() ||
                ($user->company_id === $appointment->company_id && $user->role === 'admin');
        });

        // Customers (Patients)
        Gate::define('view-customer', function (User $user, Customer $customer) {
            return $user->isSuperAdmin() || $user->company_id === $customer->company_id;
        });

        Gate::define('manage-customer', function (User $user, Customer $customer) {
            return $user->isSuperAdmin() ||
                ($user->company_id === $customer->company_id && $user->role === 'admin');
        });

        // Invoices
        Gate::define('view-invoice', function (User $user, Invoice $invoice) {
            return $user->isSuperAdmin() || $user->company_id === $invoice->company_id;
        });

        Gate::define('manage-invoice', function (User $user, Invoice $invoice) {
            return $user->isSuperAdmin() ||
                ($user->company_id === $invoice->company_id && $user->role === 'admin');
        });

        // Payments
        Gate::define('manage-payment', function (User $user, Payment $payment) {
            return $user->isSuperAdmin() ||
                ($user->company_id === $payment->company_id && $user->role === 'admin');
        });

        // Doctors
        Gate::define('manage-doctor', function (User $user, Doctor $doctor) {
            return $user->isSuperAdmin() ||
                ($user->company_id === $doctor->company_id && $user->role === 'admin');
        });

        // Procedures
        Gate::define('manage-procedure', function (User $user, Procedure $procedure) {
            return $user->isSuperAdmin() ||
                ($user->company_id === $procedure->company_id && $user->role === 'admin');
        });

        // Treatment Plans
        Gate::define('manage-treatment-plan', function (User $user, TreatmentPlan $plan) {
            return $user->isSuperAdmin() ||
                ($user->company_id === $plan->company_id && $user->role === 'admin');
        });

        // Products
        Gate::define('manage-product', function (User $user, Product $product) {
            return $user->isSuperAdmin() ||
                ($user->company_id === $product->company_id && $user->role === 'admin');
        });

        // Orders
        Gate::define('manage-order', function (User $user, Order $order) {
            return $user->isSuperAdmin() ||
                ($user->company_id === $order->company_id && $user->role === 'admin');
        });

        // Purchase Orders
        Gate::define('manage-purchase-order', function (User $user, PurchaseOrder $po) {
            return $user->isSuperAdmin() ||
                ($user->company_id === $po->company_id && $user->role === 'admin');
        });

        // Suppliers
        Gate::define('manage-supplier', function (User $user, Supplier $supplier) {
            return $user->isSuperAdmin() ||
                ($user->company_id === $supplier->company_id && $user->role === 'admin');
        });
    }

    /**
     * Register role-based Gates
     */
    protected function registerRoleGates(): void
    {
        // Admin role gates
        Gate::define('is-admin', function (User $user) {
            return $user->role === 'admin';
        });

        Gate::define('is-doctor', function (User $user) {
            return $user->role === 'doctor';
        });

        Gate::define('is-receptionist', function (User $user) {
            return $user->role === 'receptionist';
        });

        // Finance access
        Gate::define('access-finance', function (User $user) {
            return $user->isSuperAdmin() || $user->role === 'admin';
        });

        // Reports access
        Gate::define('access-reports', function (User $user) {
            return $user->isSuperAdmin() || $user->role === 'admin';
        });

        // Settings access
        Gate::define('access-settings', function (User $user) {
            return $user->isSuperAdmin() || $user->role === 'admin';
        });
    }

    /**
     * Register feature Gates
     */
    protected function registerFeatureGates(): void
    {
        // Check if company can use a feature
        Gate::define('can-use-feature', function (User $user, string $feature) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            $company = $user->company;

            if (!$company) {
                return false;
            }

            // Feature limits based on company status
            return match ($feature) {
                'sms' => $company->status === 'active',
                'whatsapp' => $company->status === 'active',
                'reports' => true,
                'export' => $company->status !== 'suspended',
                'api' => $company->status === 'active',
                default => true,
            };
        });

        // Check trial limits
        Gate::define('within-trial-limits', function (User $user, string $resource) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            $company = $user->company;

            if (!$company || $company->status !== 'trial') {
                return true;
            }

            // Trial limits
            return match ($resource) {
                'users' => $company->users()->count() < 5,
                'appointments_per_day' => $company->appointments()
                    ->whereDate('appointment_date', today())
                    ->count() < 50,
                default => true,
            };
        });
    }
}
