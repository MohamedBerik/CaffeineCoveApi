<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\SupplierController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\ReservationController;
use App\Http\Controllers\API\AdminDashboardController;
use App\Http\Controllers\API\AdminCrudController;
use App\Http\Controllers\API\AppointmentController;
// ERP
use App\Http\Controllers\API\Erp\OrderController;
use App\Http\Controllers\API\Erp\InvoicePaymentController;
use App\Http\Controllers\API\Erp\InvoiceController;
use App\Http\Controllers\API\Erp\PurchaseOrderController;
use App\Http\Controllers\API\Erp\FinanceDashboardController;
use App\Http\Controllers\API\Erp\ActivityLogController;
use App\Http\Controllers\API\Erp\CustomerStatementController;
use App\Http\Controllers\API\Erp\PaymentRefundController;
use App\Http\Controllers\API\Erp\SupplierStatementController;
use App\Http\Controllers\API\Erp\InvoiceJournalController;
use App\Http\Controllers\API\Erp\AppointmentAvailabilityController;
use App\Http\Controllers\API\Erp\CustomerCreditController;
use App\Http\Controllers\API\Erp\TreatmentPlanController;
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
        return response()->json($request->user());
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
        Route::apiResource('customers',   CustomerController::class);
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

        Route::get('/appointments/available-slots', [AppointmentAvailabilityController::class, 'index']);
        Route::post('/appointments/book', [AppointmentController::class, 'book']);
        Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
        Route::post('/appointments/{id}/complete', [AppointmentController::class, 'complete']);
        Route::post('/appointments/{id}/no-show', [AppointmentController::class, 'noShow']);

        Route::get('/customers/{customerId}/credit-balance', [CustomerCreditController::class, 'show']);

        Route::get('/treatment-plans', [TreatmentPlanController::class, 'index']);
        Route::post('/treatment-plans', [TreatmentPlanController::class, 'store']);
        Route::get('/treatment-plans/{id}', [TreatmentPlanController::class, 'show']);
        Route::put('/treatment-plans/{id}', [TreatmentPlanController::class, 'update']);
        Route::delete('/treatment-plans/{id}', [TreatmentPlanController::class, 'destroy']);

        Route::get('/treatment-plans/{id}/summary', [TreatmentPlanController::class, 'summary']);
    });

Route::middleware('auth:sanctum')->prefix('saas')->group(function () {
    Route::get('/me', [TenantController::class, 'me']);
    Route::post('/register-clinic', [ClinicOnboardingController::class, 'register']);
});
