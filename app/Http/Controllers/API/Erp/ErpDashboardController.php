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

        $today = Carbon::today();
        $monthStart = Carbon::today()->startOfMonth();
        $monthEnd = Carbon::today()->endOfMonth();

        // =================================================
        // 6. Patients KPIs
        // =================================================
        $totalPatients = \App\Models\Customer::query()
            ->where('company_id', $companyId)
            ->count();

        // =================================================
        // 1. Appointments KPIs
        // =================================================
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
            ->with(['patient:id,name,email', 'doctor:id,name'])
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->limit(5)
            ->get();

        // =================================================
        // 2. Reminders KPIs
        // =================================================
        $reminderStats = Appointment::query()
            ->where('company_id', $companyId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(reminder_status = 'pending') as pending,
                SUM(reminder_status = 'processing') as processing,
                SUM(reminder_status = 'sent') as sent,
                SUM(reminder_status = 'failed') as failed,
                SUM(reminder_status = 'skipped') as skipped
            ")
            ->first();

        $stuckRemindersCount = Appointment::query()
            ->where('company_id', $companyId)
            ->where('reminder_status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->count();

        $successRate = $reminderStats->total > 0
            ? round(($reminderStats->sent / $reminderStats->total) * 100, 2)
            : 0;

        $failedReminders = Appointment::query()
            ->where('company_id', $companyId)
            ->where('reminder_status', 'failed')
            ->orderByDesc('reminder_last_attempt_at')
            ->limit(5)
            ->get(['id', 'patient_id', 'doctor_name', 'appointment_date', 'reminder_retry_count', 'reminder_last_attempt_at']);

        // =================================================
        // 3. Invoices KPIs
        // =================================================
        $invoicesStats = Invoice::query()
            ->where('company_id', $companyId)
            ->selectRaw("
                SUM(status = 'unpaid') as unpaid,
                SUM(status = 'partially_paid') as partially_paid,
                SUM(status = 'paid') as paid
            ")
            ->first();

        $recentInvoices = Invoice::query()
            ->where('company_id', $companyId)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'number', 'customer_id', 'appointment_id', 'treatment_plan_id', 'total', 'status', 'issued_at', 'created_at']);

        // =================================================
        // 4. Revenue KPIs
        // =================================================
        $todayRevenue = (float) Payment::query()
            ->where('company_id', $companyId)
            ->whereDate('paid_at', $today)
            ->sum('applied_amount');

        $monthRevenue = (float) Payment::query()
            ->where('company_id', $companyId)
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('applied_amount');

        $recentPayments = Payment::query()
            ->where('company_id', $companyId)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'invoice_id', 'amount', 'applied_amount', 'credit_amount', 'method', 'paid_at', 'created_at']);

        // =================================================
        // 5. Customer Credits
        // =================================================
        $creditIssued = (float) DB::table('customer_credits')
            ->where('company_id', $companyId)
            ->where('type', 'credit')
            ->sum('amount');

        $creditUsed = (float) DB::table('customer_credits')
            ->where('company_id', $companyId)
            ->where('type', 'debit')
            ->sum('amount');

        $netCreditBalance = $creditIssued - $creditUsed;

        $alerts = [];

        if ($reminderStats->failed >= 10) {
            $alerts[] = [
                'type' => 'danger',
                'message' => 'High failed reminders'
            ];
        }

        if ($reminderStats->processing >= 5) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'Reminders stuck in processing'
            ];
        }

        // =================================================
        // 7. Response
        // =================================================
        return response()->json([
            'msg' => 'ERP dashboard',
            'status' => 200,
            'data' => [
                'kpis' => [
                    // Appointments
                    'today_appointments_count' => $todayAppointmentsCount,
                    'scheduled_today_count' => $scheduledTodayCount,
                    'completed_today_count' => $completedTodayCount,
                    'cancelled_today_count' => $cancelledTodayCount,
                    'no_show_today_count' => $noShowTodayCount,

                    // Reminders
                    'reminders_pending' => $reminderStats->pending,
                    'reminders_processing' => $reminderStats->processing,
                    'reminders_sent' => $reminderStats->sent,
                    'reminders_failed' => $reminderStats->failed,
                    'reminders_skipped' => $reminderStats->skipped,
                    'reminders_stuck' => $stuckRemindersCount,
                    'reminders_success_rate' => $successRate,

                    // Invoices
                    'unpaid_invoices_count' => $invoicesStats->unpaid,
                    'partially_paid_invoices_count' => $invoicesStats->partially_paid,
                    'paid_invoices_count' => $invoicesStats->paid,

                    // Revenue
                    'today_revenue' => $todayRevenue,
                    'month_revenue' => $monthRevenue,

                    // Credits
                    'credit_balance_total' => $netCreditBalance,

                    'total_patients' => $totalPatients,

                ],
                'recent_appointments' => $recentAppointments,
                'recent_invoices' => $recentInvoices,
                'recent_payments' => $recentPayments,
                'reminders' => [
                    'stats' => $reminderStats,
                    'failed_recent' => $failedReminders,
                    'alerts' => $alerts,
                ],
            ],
        ]);
    }
}
