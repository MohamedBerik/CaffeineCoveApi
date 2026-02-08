<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayment;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceDashboardController extends Controller
{
    public function index()
    {
        /* ================= KPI ================= */

        $salesToday = Invoice::whereDate('created_at', today())->sum('total');

        $paymentsToday = Payment::whereDate('created_at', today())->sum('amount');

        // لو عندك refunds في جدول مستقل عدّلها هنا
        $refundsToday = DB::table('payment_refunds')
            ->whereDate('created_at', today())
            ->sum('amount');

        $outstanding = Invoice::whereIn('status', ['unpaid', 'partially_paid'])
            ->with('payments')
            ->get()
            ->sum(function ($invoice) {
                $paid = $invoice->payments->sum('amount');
                return max($invoice->total - $paid, 0);
            });

        /* ================= charts : last 7 days sales ================= */

        $salesChart = Invoice::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total) as total')
        )
            ->whereDate('created_at', '>=', now()->subDays(6))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        /* ================= payments vs refunds ================= */

        $payments = Payment::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(amount) as total')
        )
            ->whereDate('created_at', '>=', now()->subDays(6))
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $refunds = DB::table('payment_refunds')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as total')
            )
            ->whereDate('created_at', '>=', now()->subDays(6))
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $period = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();

            $period->push([
                'date'     => $date,
                'payments' => $payments[$date]->total ?? 0,
                'refunds'  => $refunds[$date]->total ?? 0,
            ]);
        }

        /* ================= latest invoices ================= */

        $latestInvoices = Invoice::with('customer')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($i) {
                return [
                    'id'       => $i->id,
                    'number'   => $i->number ?? $i->id,
                    'customer' => $i->customer?->name,
                    'total'    => $i->total,
                    'status'   => $i->status,
                ];
            });

        /* ================= recent activity ================= */

        $activities = ActivityLog::latest()
            ->limit(8)
            ->get()
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'description' => $a->description,
                    'created_at' => $a->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'stats' => [
                'sales_today'    => $salesToday,
                'payments_today' => $paymentsToday,
                'refunds_today'  => $refundsToday,
                'outstanding'    => $outstanding,
            ],

            'sales_chart'      => $salesChart,
            'payments_chart'   => $period,
            'latest_invoices'  => $latestInvoices,
            'activities'       => $activities,
        ]);
    }
}
