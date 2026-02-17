<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

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
//-----------------------------------------------------------------------
//EPR ROUTES
//-----------------------------------------------------------------------
use App\Http\Controllers\API\Erp\OrderController;
use App\Http\Controllers\API\Erp\InvoicePaymentController;
use App\Http\Controllers\API\Erp\InvoiceController;
use App\Http\Controllers\API\Erp\PurchaseOrderController;
use App\Http\Controllers\API\Erp\FinanceDashboardController;
use App\Http\Controllers\API\Erp\ActivityLogController;
use App\Http\Controllers\API\Erp\CustomerStatementController;
use App\Http\Controllers\API\Erp\OrderInvoiceController;
use App\Http\Controllers\API\Erp\PaymentRefundController;
use App\Http\Controllers\API\Erp\SupplierStatementController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Laravel يعمل كـ API فقط
| Authentication: Sanctum Token
| بدون Sessions
| بدون CSRF
*/

//////////////////////////////////////
// Public Auth Routes (بدون Token)
//////////////////////////////////////
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

//////////////////////////////////////
// Protected Routes (Any Auth User)
//////////////////////////////////////
Route::middleware('auth:sanctum')->group(function () {

    // -------- Auth --------
    Route::get('/me', function (Request $request) {
        return response()->json($request->user());
    });

    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    });

    // -------- Reservations --------
    Route::get('/reservations',                [ReservationController::class, 'index']);
    Route::post('/reservations',               [ReservationController::class, 'store']);
    Route::post('/reservations/{id}/confirm',  [ReservationController::class, 'confirm']);
    Route::post('/reservations/{id}/cancel',   [ReservationController::class, 'cancel']);
    Route::delete('/reservations/{id}',        [ReservationController::class, 'destroy']);

    // -------- Categories --------
    Route::apiResource('categories', CategoryController::class);

    // -------- Customers --------
    Route::apiResource('customers', CustomerController::class);

    // -------- Customers --------
    Route::apiResource('suppliers', SupplierController::class);

    // -------- Employees --------
    Route::apiResource('employees', EmployeeController::class);

    // -------- Users --------
    Route::apiResource('users', UserController::class);

    // -------- Products --------
    Route::apiResource('products', ProductController::class);

    // -------- Orders --------
    // Route::apiResource('orders', OrderController::class);

    // -------- Sales --------
    Route::apiResource('sales', SaleController::class);
});

Route::middleware(['auth:sanctum', 'super.admin'])->group(function () {

    Route::get('/admin-crud/{table}', [AdminCrudController::class, 'index']);
    Route::get('/admin-crud/{table}/{id}', [AdminCrudController::class, 'show']);
    Route::post('/admin-crud/{table}', [AdminCrudController::class, 'store']);
    Route::put('/admin-crud/{table}/{id}', [AdminCrudController::class, 'update']);
    Route::delete('/admin-crud/{table}/{id}', [AdminCrudController::class, 'destroy']);
});

//////////////////////////////////////
// Admin Routes (Admin Only)
//////////////////////////////////////
Route::middleware([
    'auth:sanctum',
    'admin',
    'throttle:120,1' // ⬅️ مهم
])
    ->prefix('admin')
    ->group(function () {

        // 🔹 Dashboard
        Route::get('dashboard', [AdminDashboardController::class, 'index']);

        // // 🔹 Generic CRUD
        // Route::get('{table}',         [AdminCrudController::class, 'index']);
        // Route::get('{table}/{id}',    [AdminCrudController::class, 'show']);
        // Route::post('{table}',        [AdminCrudController::class, 'store']);
        // Route::put('{table}/{id}',    [AdminCrudController::class, 'update']);
        // Route::delete('{table}/{id}', [AdminCrudController::class, 'destroy']);
    });


Route::prefix('erp')
    ->middleware(['auth:sanctum', 'company.user'])
    ->group(function () {

        // Orders
        Route::middleware('permission:orders.manage')->post('/orders', [OrderController::class, 'storeErp']);
        Route::middleware('permission:orders.view')->get('/orders', [OrderController::class, 'indexErp']);
        Route::get('/orders/{id}', [OrderController::class, 'showErp']);
        Route::middleware('permission:orders.confirm')->post('/orders/{id}/confirm', [OrderController::class, 'confirm']);
        Route::middleware('permission:orders.cancel')->post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

        // Invoices
        Route::middleware('permission:finance.create')->post('/invoices/{invoice}/payments', [InvoicePaymentController::class, 'store']);
        Route::middleware('permission:finance.view')->get('/invoices', [InvoiceController::class, 'indexErp']);
        Route::middleware('permission:finance.view')->get('/invoices/{id}', [InvoiceController::class, 'show']);
        Route::middleware('permission:finance.view')->get('/invoices/{id}/full', [InvoiceController::class, 'showFullInvoice']);

        //Refund payment
        Route::middleware('permission:payments.refund')->post('/payments/{payment}/refund', [PaymentRefundController::class, 'refund']);

        // Purchase Orders
        Route::middleware('permission:purchases.manage')->post('/purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::middleware('permission:finance.view')->get('/purchase-orders', [PurchaseOrderController::class, 'indexErp']);
        Route::middleware('permission:finance.view')->get('/purchase-orders/{id}', [PurchaseOrderController::class, 'showErp']);
        Route::middleware('permission:purchases.receive')->post('/purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive']);
        Route::middleware('permission:purchases.return')->post('/purchase-orders/{id}/return', [PurchaseOrderController::class, 'returnItems']);
        Route::get('/purchase-orders/{id}/returnable-items', [PurchaseOrderController::class, 'getReturnableItems']);
        Route::get('/purchase-orders/{id}/returns-history', [PurchaseOrderController::class, 'returnHistory']);

        Route::middleware('permission:finance.view')->post('/purchase-orders/{id}/pay', [PurchaseOrderController::class, 'pay']);

        //Suppliers
        Route::middleware('permission:finance.view')->get('/suppliers/{supplier}/statement', [SupplierStatementController::class, 'show']);

        // Dashboard
        Route::middleware('permission:finance.view')->get('/dashboard/finance', [FinanceDashboardController::class, 'index']);

        //Activity log
        Route::middleware('permission:finance.view')->get('/activity-logs', [ActivityLogController::class, 'index']);

        //Journal entries
        Route::get('/invoices/{invoice}/journal-entries', [\App\Http\Controllers\API\Erp\InvoiceJournalController::class, 'index']);

        //Customer statement
        Route::get('/customers/{customerId}/statement', [CustomerStatementController::class, 'show']);
    });
