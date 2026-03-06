<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ErpDashboardController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $today = Carbon::today()->toDateString();
        $monthStart = Carbon::today()->startOfMonth()->toDateString();
        $monthEnd = Carbon::today()->endOfMonth()->toDateString();

        // -------------------------------------------------
        // Appointments
        // -------------------------------------------------
        $todayAppointmentsQuery = Appointment::query()
            ->where('company_id', $companyId)
            ->whereDate('appointment_date', $today);

        $todayAppointmentsCount = (clone $todayAppointmentsQuery)->count();
        $scheduledTodayCount = (clone $todayAppointmentsQuery)->where('status', 'scheduled')->count();
        $completedTodayCount = (clone $todayAppointmentsQuery)->where('status', 'completed')->count();
        $cancelledTodayCount = (clone $todayAppointmentsQuery)->where('status', 'cancelled')->count();
        $noShowTodayCount = (clone $todayAppointmentsQuery)->where('status', 'no_show')->count();

        $recentAppointments = Appointment::query()
            ->where('company_id', $companyId)
            ->with([
                'patient:id,name,email,company_id',
                'doctor:id,name,company_id',
            ])
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->limit(5)
            ->get();

        // -------------------------------------------------
        // Invoices
        // -------------------------------------------------
        $unpaidInvoicesCount = Invoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'unpaid')
            ->count();

        $partiallyPaidInvoicesCount = Invoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'partially_paid')
            ->count();

        $paidInvoicesCount = Invoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'paid')
            ->count();

        $recentInvoices = Invoice::query()
            ->where('company_id', $companyId)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'number',
                'customer_id',
                'appointment_id',
                'treatment_plan_id',
                'total',
                'status',
                'issued_at',
                'created_at',
            ]);

        // -------------------------------------------------
        // Revenue
        // today_revenue = sum(applied_amount today)
        // month_revenue = sum(applied_amount during this month)
        // -------------------------------------------------
        $todayRevenue = (float) Payment::query()
            ->where('company_id', $companyId)
            ->whereDate('paid_at', $today)
            ->sum('applied_amount');

        $monthRevenue = (float) Payment::query()
            ->where('company_id', $companyId)
            ->whereDate('paid_at', '>=', $monthStart)
            ->whereDate('paid_at', '<=', $monthEnd)
            ->sum('applied_amount');

        $recentPayments = Payment::query()
            ->where('company_id', $companyId)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'invoice_id',
                'amount',
                'applied_amount',
                'credit_amount',
                'method',
                'paid_at',
                'created_at',
            ]);

        // -------------------------------------------------
        // Customer credits
        // -------------------------------------------------
        $creditIssued = (float) DB::table('customer_credits')
            ->where('company_id', $companyId)
            ->where('type', 'credit')
            ->sum('amount');

        $creditUsed = (float) DB::table('customer_credits')
            ->where('company_id', $companyId)
            ->where('type', 'debit')
            ->sum('amount');

        $netCreditBalance = $creditIssued - $creditUsed;

        return response()->json([
            'msg' => 'ERP dashboard',
            'status' => 200,
            'data' => [
                'kpis' => [
                    'today_appointments_count' => $todayAppointmentsCount,
                    'scheduled_today_count' => $scheduledTodayCount,
                    'completed_today_count' => $completedTodayCount,
                    'cancelled_today_count' => $cancelledTodayCount,
                    'no_show_today_count' => $noShowTodayCount,

                    'unpaid_invoices_count' => $unpaidInvoicesCount,
                    'partially_paid_invoices_count' => $partiallyPaidInvoicesCount,
                    'paid_invoices_count' => $paidInvoicesCount,

                    'today_revenue' => $todayRevenue,
                    'month_revenue' => $monthRevenue,

                    'credit_balance_total' => $netCreditBalance,
                ],
                'recent_appointments' => $recentAppointments,
                'recent_invoices' => $recentInvoices,
                'recent_payments' => $recentPayments,
            ],
        ]);
    }
}
