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
// ERP
use App\Http\Controllers\API\Erp\OrderController;
use App\Http\Controllers\API\Erp\InvoicePaymentController;
use App\Http\Controllers\API\Erp\InvoiceController;
use App\Http\Controllers\API\Erp\PurchaseOrderController;
use App\Http\Controllers\API\Erp\FinanceDashboardController;
use App\Http\Controllers\API\Erp\ActivityLogController;
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
use App\Http\Controllers\API\SaaS\ClinicOnboardingController;
use App\Http\Controllers\API\SaaS\TenantController;

/*
|--------------------------------------------------------------------------
| Public auth
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Any authenticated user
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

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
            'company_id' => $user->company_id,
            'is_super_admin' => (bool) $user->is_super_admin,
            'permissions' => $permissions,
        ]);
    });

    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    });
});

/*
|--------------------------------------------------------------------------
| Super Admin only (generic database CRUD)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'super.admin'])->group(function () {

    Route::get('/admin-crud/{table}',        [AdminCrudController::class, 'index']);
    Route::get('/admin-crud/{table}/{id}',   [AdminCrudController::class, 'show']);
    Route::post('/admin-crud/{table}',        [AdminCrudController::class, 'store']);
    Route::put('/admin-crud/{table}/{id}',   [AdminCrudController::class, 'update']);
    Route::delete('/admin-crud/{table}/{id}',   [AdminCrudController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Admin dashboard (admin role – داخل شركته)
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'admin',
    'throttle:120,1'
])
    ->prefix('admin')
    ->group(function () {

        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        // Route::apiResource('customers',   CustomerController::class);
        Route::apiResource('categories',  CategoryController::class);
        Route::apiResource('suppliers',   SupplierController::class);
        Route::apiResource('employees',   EmployeeController::class);
        Route::apiResource('users',       UserController::class);
        Route::apiResource('products',    ProductController::class);
        Route::apiResource('sales',       SaleController::class);
        Route::apiResource('appointments', AppointmentController::class);

        Route::get('/reservations',                [ReservationController::class, 'index']);
        Route::post('/reservations',               [ReservationController::class, 'store']);
        Route::post('/reservations/{id}/confirm',  [ReservationController::class, 'confirm']);
        Route::post('/reservations/{id}/cancel',   [ReservationController::class, 'cancel']);
        Route::delete('/reservations/{id}',        [ReservationController::class, 'destroy']);
    });

/*
|--------------------------------------------------------------------------
| ERP routes (multi-tenant + permissions)
|--------------------------------------------------------------------------
*/

Route::prefix('erp')
    ->middleware('auth:sanctum')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Orders
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:orders.manage')
            ->post('/orders', [OrderController::class, 'storeErp']);

        Route::middleware('permission:orders.view')
            ->get('/orders', [OrderController::class, 'indexErp']);

        Route::middleware('permission:orders.view')
            ->get('/orders/{id}', [OrderController::class, 'showErp']);

        Route::middleware('permission:orders.confirm')
            ->post('/orders/{id}/confirm', [OrderController::class, 'confirm']);

        Route::middleware('permission:orders.cancel')
            ->post('/orders/{id}/cancel', [OrderController::class, 'cancel']);


        /*
        |--------------------------------------------------------------------------
        | Invoices
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:finance.view')
            ->get('/invoices', [InvoiceController::class, 'indexErp']);

        Route::middleware('permission:finance.view')
            ->get('/invoices/{id}', [InvoiceController::class, 'show']);

        Route::middleware('permission:finance.view')
            ->get('/invoices/{id}/full', [InvoiceController::class, 'showFullInvoice']);

        Route::middleware('permission:finance.create')
            ->post('/invoices/{invoice}/payments', [InvoicePaymentController::class, 'store']);

        Route::middleware('permission:finance.view')
            ->get('/invoices/{invoiceId}/journal-entries', [InvoiceJournalController::class, 'index']);


        /*
        |--------------------------------------------------------------------------
        | Payments / Refunds
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:payments.refund')
            ->post('/payments/{payment}/refund', [PaymentRefundController::class, 'refund']);


        /*
        |--------------------------------------------------------------------------
        | Purchase Orders
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:purchases.manage')
            ->post('/purchase-orders', [PurchaseOrderController::class, 'store']);

        Route::middleware('permission:finance.view')
            ->get('/purchase-orders', [PurchaseOrderController::class, 'indexErp']);

        Route::middleware('permission:finance.view')
            ->get('/purchase-orders/{id}', [PurchaseOrderController::class, 'showErp']);

        Route::middleware('permission:purchases.receive')
            ->post('/purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive']);

        Route::middleware('permission:purchases.return')
            ->post('/purchase-orders/{id}/return', [PurchaseOrderController::class, 'returnItems']);

        Route::middleware('permission:finance.view')
            ->get('/purchase-orders/{id}/returnable-items', [PurchaseOrderController::class, 'getReturnableItems']);

        Route::middleware('permission:finance.view')
            ->get('/purchase-orders/{id}/returns-history', [PurchaseOrderController::class, 'returnHistory']);

        Route::middleware('permission:finance.view')
            ->post('/purchase-orders/{id}/pay', [PurchaseOrderController::class, 'pay']);


        /*
        |--------------------------------------------------------------------------
        | Statements
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:finance.view')
            ->get('/customers/{customerId}/statement', [CustomerStatementController::class, 'show']);

        Route::middleware('permission:finance.view')
            ->get('/suppliers/{supplier}/statement', [SupplierStatementController::class, 'show']);


        /*
        |--------------------------------------------------------------------------
        | Dashboard & logs
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:finance.view')
            ->get('/dashboard/finance', [FinanceDashboardController::class, 'index']);

        Route::middleware('permission:finance.view')
            ->get('/activity-logs', [ActivityLogController::class, 'index']);

        /*
        |--------------------------------------------------------------------------
        | Appointments
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:appointments.view')
            ->get('/appointments', [AppointmentController::class, 'index']);
        Route::middleware('permission:appointments.view')
            ->get('/appointments/available-slots', [AppointmentAvailabilityController::class, 'index']);
        Route::middleware('permission:appointments.manage')
            ->post('/appointments/book', [AppointmentController::class, 'book']);
        Route::middleware('permission:appointments.manage')
            ->put('/appointments/{id}', [AppointmentController::class, 'update']);
        Route::middleware('permission:appointments.manage')
            ->post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
        Route::middleware('permission:appointments.complete')
            ->post('/appointments/{id}/complete', [AppointmentController::class, 'complete']);
        Route::middleware('permission:appointments.manage')
            ->post('/appointments/{id}/no-show', [AppointmentController::class, 'noShow']);
        Route::middleware('permission:appointments.manage')
            ->post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule']);
        Route::middleware('permission:finance.view')
            ->get('/appointments/{id}/activity', [AppointmentActivityController::class, 'index']);

        Route::get('/customers/{customerId}/credit-balance', [CustomerCreditController::class, 'show']);

        /*
        |--------------------------------------------------------------------------
        | TreatmentPlan
        |--------------------------------------------------------------------------
       */
        Route::middleware('permission:treatment_plans.view')
            ->get('/treatment-plans', [TreatmentPlanController::class, 'index']);

        Route::middleware('permission:treatment_plans.manage')
            ->post('/treatment-plans', [TreatmentPlanController::class, 'store']);

        Route::middleware('permission:treatment_plans.view')
            ->get('/treatment-plans/{id}', [TreatmentPlanController::class, 'show']);

        Route::middleware('permission:treatment_plans.manage')
            ->put('/treatment-plans/{id}', [TreatmentPlanController::class, 'update']);

        Route::middleware('permission:treatment_plans.manage')
            ->delete('/treatment-plans/{id}', [TreatmentPlanController::class, 'destroy']);

        Route::middleware('permission:treatment_plans.view')
            ->get('/treatment-plans/{id}/summary', [TreatmentPlanController::class, 'summary']);

        Route::middleware('permission:treatment_plans.view')
            ->get('/treatment-plans/{id}/cash-summary', [TreatmentPlanController::class, 'cashSummary']);

        Route::get('/treatment-plans/{id}/items', [TreatmentPlanController::class, 'items']);
        Route::post('/treatment-plans/{id}/items', [TreatmentPlanController::class, 'addItem']);
        Route::put('/treatment-plan-items/{itemId}', [TreatmentPlanController::class, 'updateItem']);
        Route::delete('/treatment-plan-items/{itemId}', [TreatmentPlanController::class, 'deleteItem']);
        Route::post('/treatment-plan-items/{itemId}/start', [TreatmentPlanController::class, 'startItem']);
        /*
        |--------------------------------------------------------------------------
        | Procedure
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:procedures.view')
            ->get('/procedures', [ProcedureController::class, 'index']);

        Route::middleware('permission:procedures.manage')
            ->post('/procedures', [ProcedureController::class, 'store']);

        Route::middleware('permission:procedures.manage')
            ->put('/procedures/{id}', [ProcedureController::class, 'update']);

        Route::middleware('permission:procedures.manage')
            ->delete('/procedures/{id}', [ProcedureController::class, 'destroy']);
        /*
        |--------------------------------------------------------------------------
        | Doctors
        |--------------------------------------------------------------------------
        */
        Route::get('/doctors', [DoctorController::class, 'index']);
        Route::post('/doctors', [DoctorController::class, 'store']);
        Route::get('/doctors/{id}', [DoctorController::class, 'show']);
        Route::put('/doctors/{id}', [DoctorController::class, 'update']);
        Route::delete('/doctors/{id}', [DoctorController::class, 'destroy']);
        Route::get('/doctors/{doctorId}/availability', [DoctorAvailabilityController::class, 'show']);

        /*
        |--------------------------------------------------------------------------
        | Customers(Patients)
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:patients.view')
            ->get('/customers', [CustomerController::class, 'index']);

        Route::middleware('permission:patients.view')
            ->get('/customers/{id}', [CustomerController::class, 'show']);

        Route::middleware('permission:patients.manage')
            ->post('/customers', [CustomerController::class, 'store']);

        Route::middleware('permission:patients.manage')
            ->put('/customers/{id}', [CustomerController::class, 'update']);

        Route::middleware('permission:patients.manage')
            ->delete('/customers/{id}', [CustomerController::class, 'destroy']);

        /*
        |--------------------------------------------------------------------------
        | DentalRecord
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:patients.view')
            ->get('/dental-records', [DentalRecordController::class, 'index']);

        Route::middleware('permission:patients.manage')
            ->post('/dental-records', [DentalRecordController::class, 'store']);

        Route::middleware('permission:patients.view')
            ->get('/dental-records/{id}', [DentalRecordController::class, 'show']);

        Route::middleware('permission:patients.manage')
            ->put('/dental-records/{id}', [DentalRecordController::class, 'update']);

        Route::middleware('permission:patients.manage')
            ->delete('/dental-records/{id}', [DentalRecordController::class, 'destroy']);

        Route::middleware('permission:treatment_plans.manage')
            ->post('/dental-records/{id}/to-treatment-plan-item', [DentalRecordController::class, 'toTreatmentPlanItem']);

        Route::middleware('permission:patients.view')
            ->get('/customers/{customerId}/profile', [PatientProfileController::class, 'show']);

        Route::middleware('permission:patients.view')
            ->get('/customers/{customerId}/timeline', [PatientTimelineController::class, 'index']);

        Route::middleware('permission:finance.view')
            ->get('/dashboard', [ErpDashboardController::class, 'index']);

        Route::get('/clinic-settings', [ClinicSettingController::class, 'show']);
        Route::put('/clinic-settings', [ClinicSettingController::class, 'update']);
    });

Route::middleware('auth:sanctum')->prefix('saas')->group(function () {
    Route::get('/me', [TenantController::class, 'me']);
    Route::post('/register-clinic', [ClinicOnboardingController::class, 'register']);
});
