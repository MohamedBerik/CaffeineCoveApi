<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Employee;
use App\Models\Sale;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $users = User::all();
        $categories = DB::table("categories")->select("cate_image","id","title_".app()->getLocale() . " as title","description_".app()->getLocale(). " as description")->get();
        $products = DB::table("products")->select("product_image","id","price","quantity","category_id","title_".app()->getLocale() . " as title","description_".app()->getLocale(). " as description")->get();
        $customers = Customer::all();
        $orders = DB::table("orders")->select("id","price","quantity","customer_id","title_".app()->getLocale() . " as title","description_".app()->getLocale(). " as description")->get();
        $employees = Employee::all();
        $sales = DB::table("sales")->select("id","price","quantity","employee_id","title_".app()->getLocale() . " as title","description_".app()->getLocale(). " as description")->get();
        $reservations = Reservation::latest()->get();
        return view('dashboard',["users"=>$users,"categories"=>$categories, "products"=>$products, "customers"=>$customers, "orders"=>$orders, "employees"=>$employees,"sales"=>$sales,"reservations"=>$reservations]);
    }
}
?>
