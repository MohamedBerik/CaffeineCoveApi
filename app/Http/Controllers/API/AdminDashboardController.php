<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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
        $companyId = $request->user()->company_id;

        return response()->json([
            'status' => 200,

            /* ========= COUNTS ========= */
            'statistics' => [
                'users'        => User::where('company_id', $companyId)->count(),
                'categories'   => Category::where('company_id', $companyId)->count(),
                'products'     => Product::where('company_id', $companyId)->count(),
                'customers'    => Customer::where('company_id', $companyId)->count(),
                'orders'       => Order::where('company_id', $companyId)->count(),
                'employees'    => Employee::where('company_id', $companyId)->count(),
                'sales'        => Sale::where('company_id', $companyId)->count(),
                'reservations' => Reservation::where('company_id', $companyId)->count(),
                'invoices'     => Invoice::where('company_id', $companyId)->count(),
                'suppliers'    => Supplier::where('company_id', $companyId)->count(),
            ],

            /* ========= LATEST DATA ========= */
            'latest' => [
                'users' => User::where('company_id', $companyId)->latest()->take(5)->get(),
                'categories' => Category::where('company_id', $companyId)->latest()->take(5)->get(),
                'products' => Product::where('company_id', $companyId)->latest()->take(5)->get(),
                'customers' => Customer::where('company_id', $companyId)->latest()->take(5)->get(),
                'orders' => Order::where('company_id', $companyId)->latest()->take(5)->get(),
                'employees' => Employee::where('company_id', $companyId)->latest()->take(5)->get(),
                'sales' => Sale::where('company_id', $companyId)->latest()->take(5)->get(),
                'reservations' => Reservation::where('company_id', $companyId)->latest()->take(5)->get(),
                'invoices' => Invoice::where('company_id', $companyId)->latest()->take(5)->get(),
                'suppliers' => Supplier::where('company_id', $companyId)->latest()->take(5)->get(),
            ]
        ]);
    }
}
