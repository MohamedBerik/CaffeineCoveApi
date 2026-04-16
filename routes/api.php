<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\SupplierController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\ReservationController;
use App\Http\Controllers\API\AdminDashboardController;
use App\Http\Controllers\API\AdminCrudController;
// ERP Controllers
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


Route::get('/test-token', function (Request $request) {
    return response()->json([
        'user' => $request->user(),
        'token_works' => true
    ]);
})->middleware('auth:sanctum');
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

    // Super Admin Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Company Admin Routes (Tenant Admin)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin', 'company.user', 'throttle:120,1'])
    ->prefix('admin')
    ->group(function () {
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('employees', EmployeeController::class);
        Route::apiResource('users', UserController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('sales', SaleController::class);
        Route::apiResource('appointments', AppointmentController::class);

        // Reservations
        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::post('/reservations', [ReservationController::class, 'store']);
        Route::post('/reservations/{id}/confirm', [ReservationController::class, 'confirm']);
        Route::post('/reservations/{id}/cancel', [ReservationController::class, 'cancel']);
        Route::delete('/reservations/{id}', [ReservationController::class, 'destroy']);
    });

/*
|--------------------------------------------------------------------------
| ERP Routes (Multi-tenant Clinic Management)
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
    });
