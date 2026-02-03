<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\ReservationController;
use App\Http\Controllers\API\AdminDashboardController;
use App\Http\Controllers\API\AdminCrudController;
use App\Http\Controllers\API\InvoicePaymentController;
use App\Http\Controllers\API\PurchaseOrderController;
use App\Http\Controllers\API\FinanceDashboardController;
use App\Http\Controllers\API\ActivityLogController;


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

    // -------- Employees --------
    Route::apiResource('employees', EmployeeController::class);

    // -------- Users --------
    Route::apiResource('users', UserController::class);

    // -------- Products --------
    Route::apiResource('products', ProductController::class);

    // -------- Orders --------
    Route::apiResource('orders', OrderController::class);

    // -------- Sales --------
    Route::apiResource('sales', SaleController::class);
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

        // 🔹 Generic CRUD
        Route::get('{table}',         [AdminCrudController::class, 'index']);
        Route::get('{table}/{id}',    [AdminCrudController::class, 'show']);
        Route::post('{table}',        [AdminCrudController::class, 'store']);
        Route::put('{table}/{id}',    [AdminCrudController::class, 'update']);
        Route::delete('{table}/{id}', [AdminCrudController::class, 'destroy']);
    });


Route::prefix('erp')->middleware('auth:sanctum')->group(function () {

    // Orders
    Route::middleware('permission:orders.manage')->post('/orders', [OrderController::class, 'storeErp']);
    Route::middleware('permission:orders.confirm')->post('/orders/{id}/confirm', [OrderController::class, 'confirm']);
    Route::middleware('permission:orders.cancel')->post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::middleware('permission:finance.view')->post('/orders/{id}/invoice', [OrderController::class, 'createInvoice']);

    // Invoices
    Route::middleware('permission:finance.view')->post('/invoices/{id}/pay', [InvoicePaymentController::class, 'pay']);
    Route::middleware('permission:finance.view')->get('/invoices/{id}', [\App\Http\Controllers\API\InvoiceController::class, 'show']);

    // Purchase Orders
    Route::middleware('permission:purchases.manage')->post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::middleware('permission:purchases.receive')->post('/purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive']);
    Route::middleware('permission:finance.view')->post('/purchase-orders/{id}/pay', [PurchaseOrderController::class, 'pay']);

    // Dashboard
    Route::middleware('permission:finance.view')->get('/dashboard/finance', [FinanceDashboardController::class, 'index']);

    //Activity log
    Route::middleware('permission:finance.view')->get('/activity-logs', [ActivityLogController::class, 'index']);

    //Journal entries
    Route::get('/invoices/{invoice}/journal-entries', [\App\Http\Controllers\API\InvoiceJournalController::class, 'index']);

    //Refund payment
    Route::post('/payments/{payment}/refund', [\App\Http\Controllers\API\PaymentRefundController::class, 'refund']);

    //for testing
    Route::get('/journal-entries/latest', function () {
        return \App\Models\JournalEntry::with('lines', 'source')
            ->latest('id')
            ->first();
    });
});
