<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\AdminCrudController;

// ERP Controllers
use App\Http\Controllers\API\Erp\SaleController;
use App\Http\Controllers\API\Erp\ReservationController;
use App\Http\Controllers\API\Erp\CategoryController;
use App\Http\Controllers\API\Erp\SupplierController;
use App\Http\Controllers\API\Erp\EmployeeController;
use App\Http\Controllers\API\Erp\ProductController;
use App\Http\Controllers\API\Erp\OrderController;
use App\Http\Controllers\API\Erp\InvoicePaymentController;
use App\Http\Controllers\API\Erp\InvoiceController;
use App\Http\Controllers\API\Erp\PurchaseOrderController;
use App\Http\Controllers\API\Erp\FinanceDashboardController;
use App\Http\Controllers\API\Erp\ActivityLogController;
use App\Http\Controllers\API\Erp\AlertController;
use App\Http\Controllers\API\Erp\AppointmentActivityController;
use App\Http\Controllers\API\Erp\CustomerStatementController;
use App\Http\Controllers\API\Erp\PaymentRefundController;
use App\Http\Controllers\API\Erp\SupplierStatementController;
use App\Http\Controllers\API\Erp\InvoiceJournalController;
use App\Http\Controllers\API\Erp\AppointmentAvailabilityController;
use App\Http\Controllers\API\Erp\CustomerCreditController;
use App\Http\Controllers\API\Erp\DoctorController;
use App\Http\Controllers\API\Erp\DoctorAvailabilityController;
use App\Http\Controllers\API\Erp\TreatmentPlanController;
use App\Http\Controllers\API\Erp\CustomerController;
use App\Http\Controllers\API\Erp\AppointmentController;
use App\Http\Controllers\API\Erp\BillingController;
use App\Http\Controllers\API\Erp\ClinicSettingController;
use App\Http\Controllers\API\Erp\DentalRecordController;
use App\Http\Controllers\API\Erp\ErpDashboardController;
use App\Http\Controllers\API\Erp\PatientProfileController;
use App\Http\Controllers\API\Erp\PatientTimelineController;
use App\Http\Controllers\API\Erp\ProcedureController;
use App\Http\Controllers\API\Erp\RadiologyController;

// SaaS Controllers
use App\Http\Controllers\API\SaaS\SaasDashboardController;
use App\Http\Controllers\API\SaaS\SaasReportsController;
use App\Http\Controllers\API\SaaS\PlatformSettingsController;
use App\Http\Controllers\API\SaaS\CompanyManagementController;
use App\Http\Controllers\API\SaaS\PlanController;
use App\Http\Controllers\API\SaaS\SubscriptionController;

// Webhook
use App\Http\Controllers\API\Webhook\PayMobWebhookController;

use App\Services\Tenant;
use Illuminate\Support\Facades\DB;



/*
|--------------------------------------------------------------------------
| OPTIONS route fallback
|--------------------------------------------------------------------------
*/

// Route::options('{any}', function () {
//     return response('', 200)
//         ->header('Access-Control-Allow-Origin', '*')
//         ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
//         ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, X-Tenant-ID')
//         ->header('Access-Control-Allow-Credentials', 'true')
//         ->header('Access-Control-Max-Age', '86400');
// })->where('any', '.*');


/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');

/*
|--------------------------------------------------------------------------
| Webhooks (No Auth)
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:webhooks')->group(function () {
    Route::post('/webhooks/paymob', [PayMobWebhookController::class, 'handle']);
});

/*
|--------------------------------------------------------------------------
| Super Admin Dynamic CRUD
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'super.admin'])->prefix('admin')->group(function () {
    Route::get('/crud/{table}', [AdminCrudController::class, 'index']);
    Route::get('/crud/{table}/{id}', [AdminCrudController::class, 'show']);
    Route::post('/crud/{table}', [AdminCrudController::class, 'store']);
    Route::put('/crud/{table}/{id}', [AdminCrudController::class, 'update']);
    Route::delete('/crud/{table}/{id}', [AdminCrudController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes (All Users)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'company.user'])->group(function () {

    // User Profile
    Route::get('/me', function (Request $request) {
        $user = $request->user();
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        if ($user->is_super_admin) {
            $permissions = ['*'];
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'roles' => $user->getRoleNames(),
            'company_id' => Tenant::id(),
            'is_super_admin' => (bool) $user->is_super_admin,
            'permissions' => $permissions,
        ]);
    });

    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    });

    // Tenant Context
    Route::get('/companies', function () {
        return \App\Models\Company::select('id', 'name', 'slug', 'status')->get();
    });

    Route::post('/switch-company', function (Request $request) {
        $companyId = $request->company_id;

        if ($companyId === null || $companyId === 'null') {
            session(['tenant_id' => null]);
            return response()->json(['message' => 'Switched to global mode']);
        }

        $company = \App\Models\Company::find($companyId);
        if (!$company) {
            return response()->json(['message' => 'Invalid company'], 404);
        }

        session(['tenant_id' => $companyId]);

        return response()->json([
            'message' => 'Company switched successfully',
            'company' => $company->only('id', 'name', 'slug')
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| ERP Routes (Multi-tenant Clinic Management)
|--------------------------------------------------------------------------
*/
Route::prefix('erp')
    ->middleware(['auth:sanctum', 'company.user', 'subscription.active', 'throttle:api'])
    ->group(function () {

        // ==================== DASHBOARD & REPORTS ====================
        Route::middleware('permission:finance.view')->group(function () {
            Route::get('/dashboard', [ErpDashboardController::class, 'index']);
            Route::get('/dashboard/finance', [FinanceDashboardController::class, 'index']);
        });

        Route::middleware('permission:activity_logs.view')->group(function () {
            Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        });

        // ==================== ALERTS ====================
        Route::middleware('permission:finance.view')->group(function () {
            Route::get('/alerts', [AlertController::class, 'index']);
            Route::get('/alerts/unread-count', [AlertController::class, 'unreadCount']);
            Route::post('/alerts/{id}/ack', [AlertController::class, 'acknowledge']);
            Route::post('/alerts/mark-all-read', [AlertController::class, 'markAllRead']);
        });

        // ==================== BILLING ====================
        Route::middleware('throttle:billing')->group(function () {
            Route::get('/billing/subscription', [BillingController::class, 'currentSubscription']);
            Route::get('/billing/invoices', [BillingController::class, 'invoices']);
            Route::get('/billing/plans', [BillingController::class, 'availablePlans']);
            Route::get('/billing/payment-methods', [BillingController::class, 'paymentMethods']);
            Route::get('/billing/status', [BillingController::class, 'status']);
        });

        Route::middleware('throttle:payment')->group(function () {
            Route::post('/billing/subscribe', [BillingController::class, 'subscribe']);
            Route::post('/billing/cancel', [BillingController::class, 'cancel']);
            Route::post('/billing/cancel-pending/{id}', [BillingController::class, 'cancelPending']);
            Route::post('/billing/payment-methods', [BillingController::class, 'addPaymentMethod']);
            Route::delete('/billing/payment-methods/{id}', [BillingController::class, 'removePaymentMethod']);
            Route::post('/billing/change', [BillingController::class, 'change']);
        });

        // ==================== ADMIN PANEL ====================
        Route::middleware(['admin', 'permission:users.manage'])->group(function () {
            Route::apiResource('admin/users', UserController::class);
        });

        // ==================== ORDERS ====================
        Route::middleware('permission:orders.manage')->post('/orders', [OrderController::class, 'storeErp']);
        Route::middleware('permission:orders.view')->group(function () {
            Route::get('/orders', [OrderController::class, 'indexErp']);
            Route::get('/orders/{id}', [OrderController::class, 'showErp']);
        });
        Route::middleware('permission:orders.confirm')->post('/orders/{id}/confirm', [OrderController::class, 'confirm']);
        Route::middleware('permission:orders.cancel')->post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

        // ==================== INVOICES ====================
        Route::middleware('permission:finance.view')->group(function () {
            Route::get('/invoices', [InvoiceController::class, 'indexErp']);
            Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
            Route::get('/invoices/{id}/full', [InvoiceController::class, 'showFullInvoice']);
            Route::get('/invoices/{invoiceId}/journal-entries', [InvoiceJournalController::class, 'index']);
        });
        Route::middleware('permission:finance.create')->group(function () {
            Route::post('/invoices/{invoice}/payments', [InvoicePaymentController::class, 'store']);
            Route::post('/invoices/{invoice}/apply-credit', [InvoicePaymentController::class, 'applyCustomerCredit']);
        });

        // ==================== PAYMENTS & REFUNDS ====================
        Route::middleware('permission:payments.refund')->post('/payments/{payment}/refund', [PaymentRefundController::class, 'refund']);

        // ==================== PURCHASE ORDERS ====================
        Route::middleware('permission:purchases.manage')->post('/purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::middleware('permission:finance.view')->group(function () {
            Route::get('/purchase-orders', [PurchaseOrderController::class, 'indexErp']);
            Route::get('/purchase-orders/{id}', [PurchaseOrderController::class, 'showErp']);
            Route::get('/purchase-orders/{id}/returnable-items', [PurchaseOrderController::class, 'getReturnableItems']);
            Route::get('/purchase-orders/{id}/returns-history', [PurchaseOrderController::class, 'returnHistory']);
            Route::post('/purchase-orders/{id}/pay', [PurchaseOrderController::class, 'pay']);
        });
        Route::middleware('permission:purchases.receive')->post('/purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive']);
        Route::middleware('permission:purchases.return')->post('/purchase-orders/{id}/return', [PurchaseOrderController::class, 'returnItems']);

        // ==================== STATEMENTS ====================
        Route::middleware('permission:finance.view')->group(function () {
            Route::get('/customers/{customerId}/statement', [CustomerStatementController::class, 'show']);
            Route::get('/suppliers/{supplier}/statement', [SupplierStatementController::class, 'show']);
            Route::get('/customers/{customerId}/credit-balance', [CustomerCreditController::class, 'show']);
        });

        // ==================== APPOINTMENTS ====================
        Route::middleware('permission:appointments.view')->group(function () {
            Route::get('/appointments', [AppointmentController::class, 'index']);
            Route::get('/appointments/available-slots', [AppointmentAvailabilityController::class, 'index']);
            Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
            Route::get('/appointments/{id}/activity', [AppointmentActivityController::class, 'index']);
        });
        Route::middleware('permission:appointments.manage')->group(function () {
            Route::post('/appointments/book', [AppointmentController::class, 'book']);
            Route::put('/appointments/{id}', [AppointmentController::class, 'update']);
            Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
            Route::post('/appointments/{id}/no-show', [AppointmentController::class, 'noShow']);
            Route::post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule']);
            Route::post('/appointments/{id}/send-reminder', [AppointmentController::class, 'sendReminder']);
        });
        Route::middleware('permission:appointments.complete')->post('/appointments/{id}/complete', [AppointmentController::class, 'complete']);

        // ==================== TREATMENT PLANS ====================
        Route::middleware('permission:treatment_plans.view')->group(function () {
            Route::get('/treatment-plans', [TreatmentPlanController::class, 'index']);
            Route::get('/treatment-plans/{id}', [TreatmentPlanController::class, 'show']);
            Route::get('/treatment-plans/{id}/summary', [TreatmentPlanController::class, 'summary']);
            Route::get('/treatment-plans/{id}/cash-summary', [TreatmentPlanController::class, 'cashSummary']);
            Route::get('/treatment-plans/{itemId}/items', [TreatmentPlanController::class, 'items']);
        });
        Route::middleware('permission:treatment_plans.manage')->group(function () {
            Route::post('/treatment-plans', [TreatmentPlanController::class, 'store']);
            Route::put('/treatment-plans/{id}', [TreatmentPlanController::class, 'update']);
            Route::delete('/treatment-plans/{id}', [TreatmentPlanController::class, 'destroy']);
            Route::post('/treatment-plans/{itemId}/items', [TreatmentPlanController::class, 'addItem']);
            Route::put('/treatment-plan-items/{itemId}', [TreatmentPlanController::class, 'updateItem']);
            Route::delete('/treatment-plan-items/{itemId}', [TreatmentPlanController::class, 'deleteItem']);
            Route::post('/treatment-plan-items/{itemId}/start', [TreatmentPlanController::class, 'startItem']);
            Route::post('/treatment-plan-items/{itemId}/attach-appointment', [TreatmentPlanController::class, 'attachAppointment']);
        });

        // ==================== PROCEDURES ====================
        Route::middleware('permission:procedures.view')->group(function () {
            Route::get('/procedures', [ProcedureController::class, 'index']);
            Route::get('/procedures/{id}', [ProcedureController::class, 'show']);
        });
        Route::middleware('permission:procedures.manage')->group(function () {
            Route::post('/procedures', [ProcedureController::class, 'store']);
            Route::put('/procedures/{id}', [ProcedureController::class, 'update']);
            Route::delete('/procedures/{id}', [ProcedureController::class, 'destroy']);
        });

        // ==================== DOCTORS ====================
        Route::middleware('permission:doctors.view')->group(function () {
            Route::get('/doctors', [DoctorController::class, 'index']);
            Route::get('/doctors/{id}', [DoctorController::class, 'show']);
            Route::get('/doctors/{doctorId}/availability', [DoctorAvailabilityController::class, 'show']);
        });
        Route::middleware('permission:doctors.manage')->group(function () {
            Route::post('/doctors', [DoctorController::class, 'store']);
            Route::put('/doctors/{id}', [DoctorController::class, 'update']);
            Route::delete('/doctors/{id}', [DoctorController::class, 'destroy']);
        });

        // ==================== CUSTOMERS (PATIENTS) ====================
        Route::middleware('permission:patients.view')->group(function () {
            Route::get('/customers', [CustomerController::class, 'index']);
            Route::get('/customers/{id}', [CustomerController::class, 'show']);
            Route::get('/customers/{customerId}/profile', [PatientProfileController::class, 'show']);
            Route::get('/customers/{customerId}/timeline', [PatientTimelineController::class, 'index']);
        });
        Route::middleware('permission:patients.manage')->group(function () {
            Route::post('/customers', [CustomerController::class, 'store']);
            Route::put('/customers/{id}', [CustomerController::class, 'update']);
            Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);
        });

        // ==================== RADIOLOGY ====================
        Route::middleware('permission:radiology.view')->group(function () {
            Route::get('/patient-radiologies', [RadiologyController::class, 'index']);
            Route::get('/patient-radiologies/{id}', [RadiologyController::class, 'show']);
        });
        Route::middleware('permission:radiology.manage')->group(function () {
            Route::post('/patient-radiologies', [RadiologyController::class, 'store']);
            Route::delete('/patient-radiologies/{id}', [RadiologyController::class, 'destroy']);
        });

        // ==================== DENTAL RECORDS ====================
        Route::middleware('permission:patients.view')->group(function () {
            Route::get('/dental-records', [DentalRecordController::class, 'index']);
            Route::get('/dental-records/{id}', [DentalRecordController::class, 'show']);
        });
        Route::middleware('permission:patients.manage')->group(function () {
            Route::post('/dental-records', [DentalRecordController::class, 'store']);
            Route::put('/dental-records/{id}', [DentalRecordController::class, 'update']);
            Route::delete('/dental-records/{id}', [DentalRecordController::class, 'destroy']);
        });
        Route::middleware('permission:treatment_plans.manage')
            ->post('/dental-records/{id}/to-treatment-plan-item', [DentalRecordController::class, 'toTreatmentPlanItem']);

        // ==================== CLINIC SETTINGS ====================
        Route::middleware('permission:settings.view')->get('/clinic-settings', [ClinicSettingController::class, 'show']);
        Route::middleware('permission:settings.manage')->put('/clinic-settings', [ClinicSettingController::class, 'update']);

        // ==================== INVENTORY ====================
        Route::middleware('permission:inventory.view')->group(function () {
            Route::get('/categories', [CategoryController::class, 'index']);
            Route::get('/categories/{id}', [CategoryController::class, 'show']);
            Route::get('/suppliers', [SupplierController::class, 'index']);
            Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
            Route::get('/products', [ProductController::class, 'index']);
            Route::get('/products/{id}', [ProductController::class, 'show']);
        });
        Route::middleware('permission:inventory.manage')->group(function () {
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{id}', [CategoryController::class, 'update']);
            Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
            Route::post('/suppliers', [SupplierController::class, 'store']);
            Route::put('/suppliers/{id}', [SupplierController::class, 'update']);
            Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);
            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        });

        // ==================== EMPLOYEES ====================
        Route::middleware('permission:employees.view')->group(function () {
            Route::get('/employees', [EmployeeController::class, 'index']);
            Route::get('/employees/{id}', [EmployeeController::class, 'show']);
        });
        Route::middleware('permission:employees.manage')->group(function () {
            Route::post('/employees', [EmployeeController::class, 'store']);
            Route::put('/employees/{id}', [EmployeeController::class, 'update']);
            Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
        });

        // ==================== SALES ====================
        Route::middleware('permission:sales.view')->group(function () {
            Route::get('/sales', [SaleController::class, 'index']);
            Route::get('/sales/{id}', [SaleController::class, 'show']);
        });
        Route::middleware('permission:sales.manage')->group(function () {
            Route::post('/sales', [SaleController::class, 'store']);
            Route::put('/sales/{id}', [SaleController::class, 'update']);
            Route::delete('/sales/{id}', [SaleController::class, 'destroy']);
        });

        // ==================== RESERVATIONS ====================
        Route::middleware('permission:reservations.view')->group(function () {
            Route::get('/reservations', [ReservationController::class, 'index']);
        });
        Route::middleware('permission:reservations.manage')->group(function () {
            Route::post('/reservations', [ReservationController::class, 'store']);
            Route::post('/reservations/{id}/confirm', [ReservationController::class, 'confirm']);
            Route::post('/reservations/{id}/cancel', [ReservationController::class, 'cancel']);
            Route::delete('/reservations/{id}', [ReservationController::class, 'destroy']);
        });
    });

/*
|--------------------------------------------------------------------------
| SaaS Routes (Super Admin Only)
|--------------------------------------------------------------------------
*/
Route::prefix('saas')
    ->middleware(['auth:sanctum', 'super.admin'])
    ->group(function () {

        // ==================== DASHBOARD & REPORTS ====================
        Route::get('/dashboard', [SaasDashboardController::class, 'index']);
        Route::get('/reports', [SaasReportsController::class, 'index']);
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);

        // ==================== COMPANIES MANAGEMENT ====================
        Route::get('/companies', [CompanyManagementController::class, 'index']);
        Route::post('/companies', [CompanyManagementController::class, 'store']);
        Route::get('/companies/{id}', [CompanyManagementController::class, 'show']);
        Route::put('/companies/{id}', [CompanyManagementController::class, 'update']);
        Route::delete('/companies/{id}', [CompanyManagementController::class, 'destroy']);
        Route::post('/companies/{id}/suspend', [CompanyManagementController::class, 'suspend']);
        Route::post('/companies/{id}/activate', [CompanyManagementController::class, 'activate']);
        Route::get('/companies/{id}/stats', [CompanyManagementController::class, 'stats']);
        Route::get('/companies/{id}/users', [CompanyManagementController::class, 'users']);
        Route::get('/companies/{id}/subscriptions', [CompanyManagementController::class, 'subscriptions']);

        // ==================== PLANS MANAGEMENT ====================
        Route::get('/plans', [PlanController::class, 'index']);
        Route::post('/plans', [PlanController::class, 'store']);
        Route::get('/plans/{id}', [PlanController::class, 'show']);
        Route::put('/plans/{id}', [PlanController::class, 'update']);
        Route::delete('/plans/{id}', [PlanController::class, 'destroy']);
        Route::put('/plans/{id}/toggle', [PlanController::class, 'toggle']);

        // ==================== SUBSCRIPTIONS MANAGEMENT ====================
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::post('/subscriptions', [SubscriptionController::class, 'store']);
        Route::put('/subscriptions/{id}', [SubscriptionController::class, 'update']);
        Route::post('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/subscriptions/{id}/renew', [SubscriptionController::class, 'renew']);

        // ==================== SETTINGS ====================
        Route::get('/settings', [PlatformSettingsController::class, 'index']);
        Route::put('/settings', [PlatformSettingsController::class, 'update']);

        // ==================== ADMIN POWER TOOLS ====================
        Route::post('/companies/{id}/force-cancel-subscription', [CompanyManagementController::class, 'forceCancelSubscription']);
        Route::post('/companies/{id}/adjust-billing', [CompanyManagementController::class, 'adjustBilling']);
        Route::post('/companies/{id}/impersonate', [CompanyManagementController::class, 'impersonate']);
    });

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    $checks = [
        'database' => false,
        'queue' => false,
        'cache' => false,
        'storage' => false,
    ];

    // Check Database
    try {
        DB::connection()->getPdo();
        $checks['database'] = true;
    } catch (\Exception $e) {
        //
    }

    // Check Queue
    try {
        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();
        $checks['queue'] = $failed < 10; // ✅ مقبول لو أقل من 10 فشل
    } catch (\Exception $e) {
        //
    }

    // Check Cache
    try {
        \Illuminate\Support\Facades\Cache::set('health_check', 'ok', 10);
        $checks['cache'] = \Illuminate\Support\Facades\Cache::get('health_check') === 'ok';
    } catch (\Exception $e) {
        //
    }

    // Check Storage
    try {
        $checks['storage'] = is_writable(storage_path());
    } catch (\Exception $e) {
        //
    }

    $healthy = !in_array(false, $checks);
    $statusCode = $healthy ? 200 : 500;

    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'timestamp' => now()->toIso8601String(),
        'version' => app()->version(),
        'environment' => app()->environment(),
        'checks' => $checks,
    ], $statusCode);
});
