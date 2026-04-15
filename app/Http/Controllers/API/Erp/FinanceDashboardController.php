<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayment;
use App\Models\PaymentRefund;
use App\Models\ActivityLog;
use App\Services\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FinanceDashboardController extends Controller
{
    public function index(Request $request)
    {
        $companyId = Tenant::id();

        /* ============================================================
         | Old data (KEEP – backward compatible)
         * ============================================================ */

        $totalSales = Invoice::query()->sum('total');

        $totalCollected = Payment::query()->sum('amount');

        $totalPurchases = PurchaseOrder::query()->sum('total');

        $totalPaidToSuppliers = SupplierPayment::query()->sum('amount');

        $receivables = Invoice::query()
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->with('payments')
            ->get()
            ->sum(function ($invoice) {
                $paid = $invoice->payments->sum('amount');
                return max($invoice->total - $paid, 0);
            });

        $payables = PurchaseOrder::query()
            ->whereNotIn('status', ['cancelled'])
            ->with('payments')
            ->get()
            ->sum(function ($po) {
                $paid = $po->payments->sum('amount');
                return max($po->total - $paid, 0);
            });

        /* ============================================================
         | Financial collection breakdown
         * ============================================================ */

        $grossCollected = Payment::query()->sum('amount');

        $refundsTotal = PaymentRefund::query()->sum('amount');

        $netCollected = $grossCollected - $refundsTotal;

        /* ============================================================
         | New dashboard KPIs
         * ============================================================ */

        $today = Carbon::today();

        $salesToday = Invoice::query()
            ->whereDate('created_at', $today)
            ->sum('total');

        $paymentsToday = Payment::query()
            ->whereDate('created_at', $today)
            ->sum('amount');

        $refundsToday = PaymentRefund::query()
            ->whereDate('created_at', $today)
            ->sum('amount');

        $outstanding = $receivables;

        /* ============================================================
         | Charts – last 7 days
         * ============================================================ */

        $period = collect();

        for ($i = 6; $i >= 0; $i--) {

            $date = Carbon::today()->subDays($i)->toDateString();

            $sales = Invoice::query()
                ->whereDate('created_at', $date)
                ->sum('total');

            $payments = Payment::query()
                ->whereDate('created_at', $date)
                ->sum('amount');

            $refunds = PaymentRefund::query()
                ->whereDate('created_at', $date)
                ->sum('amount');

            $period->push([
                'date'     => $date,
                'total'    => $sales,
                'payments' => $payments,
                'refunds'  => $refunds,
            ]);
        }

        $salesChart = $period->map(fn($r) => [
            'date'  => $r['date'],
            'total' => $r['total'],
        ]);

        $paymentsChart = $period->map(fn($r) => [
            'date'     => $r['date'],
            'payments' => $r['payments'],
            'refunds'  => $r['refunds'],
        ]);

        /* ============================================================
         | Latest invoices
         * ============================================================ */

        $latestInvoices = Invoice::query()
            ->with('customer')
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

        /* ============================================================
         | Recent activity
         * ============================================================ */

        $activities = ActivityLog::query()
            ->latest()
            ->limit(6)
            ->get()
            ->map(function ($a) {
                return [
                    'id'          => $a->id,
                    'description' => $a->description,
                    'created_at'  => $a->created_at->diffForHumans(),
                ];
            });

        return response()->json([

            /* ===== new dashboard shape ===== */

            'stats' => [
                'sales_today'    => $salesToday,
                'payments_today' => $paymentsToday,
                'refunds_today'  => $refundsToday,
                'outstanding'    => $outstanding,
            ],

            'sales_chart'     => $salesChart->values(),
            'payments_chart'  => $paymentsChart->values(),
            'latest_invoices' => $latestInvoices,
            'activities'      => $activities,

            /* ===== old data (DO NOT BREAK) ===== */

            'total_sales' => $totalSales,
            'total_collected' => $totalCollected,
            'total_purchases' => $totalPurchases,
            'total_paid_to_suppliers' => $totalPaidToSuppliers,
            'receivables' => $receivables,
            'payables' => $payables,

            /* ===== added fields (no breaking change) ===== */

            'gross_collected' => $grossCollected,
            'refunds_total'   => $refundsTotal,
            'net_collected'   => $netCollected,
        ]);
    }
}
