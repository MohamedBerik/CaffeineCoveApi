<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Reservation;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 200,

            /* ========= COUNTS ========= */
            'statistics' => [
                'users'         => User::count(),
                'categories'    => Category::count(),
                'products'      => Product::count(),
                'customers'     => Customer::count(),
                'orders'        => Order::count(),
                'employees'     => Employee::count(),
                'sales'         => Sale::count(),
                'reservations'  => Reservation::count(),
            ],

            /* ========= LATEST DATA ========= */
            'latest' => [
                'users' => User::latest()->take(5)->get(),
                'categories' => Category::latest()->take(5)->get(),
                'products' => Product::latest()->take(5)->get(),
                'customers' => Customer::latest()->take(5)->get(),
                'orders' => Order::latest()->take(5)->get(),
                'employees' => Employee::latest()->take(5)->get(),
                'sales' => Sale::latest()->take(5)->get(),
                'reservations' => Reservation::latest()->take(5)->get(),
            ]
        ]);
    }
}
