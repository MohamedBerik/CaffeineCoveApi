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
use App\Http\Controllers\API\Erp\ClinicSettingController;
use App\Http\Controllers\API\Erp\DentalRecordController;
use App\Http\Controllers\API\Erp\ErpDashboardController;
use App\Http\Controllers\API\Erp\PatientProfileController;
use App\Http\Controllers\API\Erp\PatientTimelineController;
use App\Http\Controllers\API\Erp\ProcedureController;
use App\Http\Controllers\API\Erp\RadiologyController;
use App\Services\Tenant;

use App\Http\Controllers\API\SaaS\TenantController;
use App\Http\Controllers\API\SaaS\ClinicOnboardingController;

/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

/*
|--------------------------------------------------------------------------
| Authenticated Routes (All Users)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'company.user'])->group(function () {

    // User Profile
    Route::get('/me', function (Request $request) {
        $user = $request->user();

        $permissions = [];

        if ($user->is_super_admin || $user->role === 'admin') {
            $permissions = [
                'finance.view',
                'finance.create',
                'orders.view',
                'orders.manage',
                'orders.confirm',
                'orders.cancel',
                'payments.refund',
                'purchases.manage',
                'purchases.receive',
                'purchases.return',
                'appointments.view',
                'appointments.manage',
                'appointments.complete',
                'treatment_plans.view',
                'treatment_plans.manage',
                'procedures.view',
                'procedures.manage',
                'patients.view',
                'patients.manage',
            ];
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'company_id' => Tenant::id(),
            'is_super_admin' => (bool) $user->is_super_admin,
            'permissions' => $permissions,
        ]);
    });

    // Logout
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    });
});

/*
|--------------------------------------------------------------------------
| Super Admin Routes (Global Access)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'super.admin'])->prefix('admin')->group(function () {
    // Generic CRUD for any table
    Route::get('/crud/{table}', [AdminCrudController::class, 'index']);
    Route::get('/crud/{table}/{id}', [AdminCrudController::class, 'show']);
    Route::post('/crud/{table}', [AdminCrudController::class, 'store']);
    Route::put('/crud/{table}/{id}', [AdminCrudController::class, 'update']);
    Route::delete('/crud/{table}/{id}', [AdminCrudController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Company Admin Routes (Tenant Admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'admin', 'company.user', 'throttle:120,1'])
    ->prefix('admin')
    ->group(function () {

        // Users
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        // Appointments
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
        Route::put('/appointments/{id}', [AppointmentController::class, 'update']);
        Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy']);
    });

/*
|--------------------------------------------------------------------------
| ERP Routes (Multi-tenant Clinic Management)
|--------------------------------------------------------------------------
| ✅ SetTenant Middleware removed - Now in Kernel.php (Global)
|--------------------------------------------------------------------------
*/
Route::prefix('erp')
    ->middleware(['auth:sanctum', 'company.user'])
    ->group(function () {

        // ==================== DASHBOARD & REPORTS ====================
        Route::middleware('permission:finance.view')->group(function () {
            Route::get('/dashboard', [ErpDashboardController::class, 'index']);
            Route::get('/dashboard/finance', [FinanceDashboardController::class, 'index']);
            Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        });

        // ==================== ALERTS ====================
        Route::middleware('permission:finance.view')->group(function () {
            Route::get('/alerts', [AlertController::class, 'index']);
            Route::get('/alerts/unread-count', [AlertController::class, 'unreadCount']);
            Route::post('/alerts/{id}/ack', [AlertController::class, 'acknowledge']);
            Route::post('/alerts/mark-all-read', [AlertController::class, 'markAllRead']);
        });

        // ==================== ORDERS ====================
        Route::middleware('permission:orders.manage')->post('/orders', [OrderController::class, 'storeErp']);
        Route::middleware('permission:orders.view')->get('/orders', [OrderController::class, 'indexErp']);
        Route::middleware('permission:orders.view')->get('/orders/{id}', [OrderController::class, 'showErp']);
        Route::middleware('permission:orders.confirm')->post('/orders/{id}/confirm', [OrderController::class, 'confirm']);
        Route::middleware('permission:orders.cancel')->post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

        // ==================== INVOICES ====================
        Route::middleware('permission:finance.view')->group(function () {
            Route::get('/invoices', [InvoiceController::class, 'indexErp']);
            Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
            Route::get('/invoices/{id}/full', [InvoiceController::class, 'showFullInvoice']);
            Route::get('/invoices/{invoiceId}/journal-entries', [InvoiceJournalController::class, 'index']);
        });
        Route::middleware('permission:finance.create')->post('/invoices/{invoice}/payments', [InvoicePaymentController::class, 'store']);
        Route::post('/invoices/{invoice}/apply-credit', [InvoicePaymentController::class, 'applyCustomerCredit']);

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
        Route::get('/doctors', [DoctorController::class, 'index']);
        Route::post('/doctors', [DoctorController::class, 'store']);
        Route::get('/doctors/{id}', [DoctorController::class, 'show']);
        Route::put('/doctors/{id}', [DoctorController::class, 'update']);
        Route::delete('/doctors/{id}', [DoctorController::class, 'destroy']);
        Route::get('/doctors/{doctorId}/availability', [DoctorAvailabilityController::class, 'show']);

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
        Route::get('/patient-radiologies', [RadiologyController::class, 'index']);
        Route::post('/patient-radiologies', [RadiologyController::class, 'store']);
        Route::get('/patient-radiologies/{id}', [RadiologyController::class, 'show']);
        Route::delete('/patient-radiologies/{id}', [RadiologyController::class, 'destroy']);

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
        Route::get('/clinic-settings', [ClinicSettingController::class, 'show']);
        Route::put('/clinic-settings', [ClinicSettingController::class, 'update']);

        // ==================== categories  ====================
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::get('/categories/{id}', [CategoryController::class, 'show']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // ==================== suppliers ====================
        Route::get('/suppliers', [SupplierController::class, 'index']);
        Route::post('/suppliers', [SupplierController::class, 'store']);
        Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
        Route::put('/suppliers/{id}', [SupplierController::class, 'update']);
        Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);

        // ==================== employees ====================
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::get('/employees/{id}', [EmployeeController::class, 'show']);
        Route::put('/employees/{id}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);

        // ==================== products ====================
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products/{id}', [ProductController::class, 'show']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);

        // ==================== sales ====================
        Route::get('/sales', [SaleController::class, 'index']);
        Route::post('/sales', [SaleController::class, 'store']);
        Route::get('/sales/{id}', [SaleController::class, 'show']);
        Route::put('/sales/{id}', [SaleController::class, 'update']);
        Route::delete('/sales/{id}', [SaleController::class, 'destroy']);

        // ==================== reservations ====================
        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::post('/reservations', [ReservationController::class, 'store']);
        Route::post('/reservations/{id}/confirm', [ReservationController::class, 'confirm']);
        Route::post('/reservations/{id}/cancel', [ReservationController::class, 'cancel']);
        Route::delete('/reservations/{id}', [ReservationController::class, 'destroy']);
    });

/*
|--------------------------------------------------------------------------
| SaaS Routes (Super Admin Only)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    // ✅ جلب قائمة الشركات (لـ Super Admin)
    Route::get('/companies', function () {
        if (!auth()->user()->is_super_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return \App\Models\Company::select('id', 'name', 'slug', 'status')->get();
    });

    // ✅ تبديل الشركة (لـ Super Admin)
    Route::post('/switch-company', function (Request $request) {
        $user = auth()->user();

        if (!$user->is_super_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $companyId = $request->company_id;

        // ✅ لو عايز يرجع لـ Global Mode (بدون شركة)
        if ($companyId === null || $companyId === 'null') {
            session(['tenant_id' => null]);
            return response()->json(['message' => 'Switched to global mode']);
        }

        // ✅ التحقق من وجود الشركة
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
