<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemAlert;
use App\Services\ReminderAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Services\InsightService;

class ErpDashboardController extends Controller
{

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $data = Cache::remember(
            "dashboard_{$companyId}",
            60, // ⏱️ ثانية
            function () use ($companyId, $today, $monthStart, $monthEnd) {

                // =================================================
                // 1. Patients
                // =================================================
                $totalPatients = Customer::query()
                    ->where('company_id', $companyId)
                    ->count();

                // =================================================
                // 2. Appointments
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
                // 3. Reminders
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

                if (!$reminderStats) {
                    $reminderStats = (object)[
                        'total' => 0,
                        'pending' => 0,
                        'processing' => 0,
                        'sent' => 0,
                        'failed' => 0,
                        'skipped' => 0
                    ];
                }

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
                    ->get();

                // =================================================
                // 4. Invoices
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
                    ->latest()
                    ->limit(5)
                    ->get();

                // =================================================
                // 5. Revenue
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
                    ->latest()
                    ->limit(5)
                    ->get();

                // =================================================
                // 6. Credits
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

                // =================================================
                // RETURN DATA
                // =================================================
                return [
                    'kpis' => [
                        'today_appointments_count' => $todayAppointmentsCount,
                        'scheduled_today_count' => $scheduledTodayCount,
                        'completed_today_count' => $completedTodayCount,
                        'cancelled_today_count' => $cancelledTodayCount,
                        'no_show_today_count' => $noShowTodayCount,

                        'reminders_pending' => $reminderStats->pending,
                        'reminders_processing' => $reminderStats->processing,
                        'reminders_sent' => $reminderStats->sent,
                        'reminders_failed' => $reminderStats->failed,
                        'reminders_skipped' => $reminderStats->skipped,
                        'reminders_stuck' => $stuckRemindersCount,
                        'reminders_success_rate' => $successRate,

                        'unpaid_invoices_count' => $invoicesStats->unpaid,
                        'partially_paid_invoices_count' => $invoicesStats->partially_paid,
                        'paid_invoices_count' => $invoicesStats->paid,

                        'today_revenue' => $todayRevenue,
                        'month_revenue' => $monthRevenue,

                        'credit_balance_total' => $netCreditBalance,
                        'total_patients' => $totalPatients,
                    ],
                    'recent_appointments' => $recentAppointments,
                    'recent_invoices' => $recentInvoices,
                    'recent_payments' => $recentPayments,
                    'reminders' => [
                        'stats' => $reminderStats,
                        'failed_recent' => $failedReminders,
                    ],
                ];
            }
        );

        $insights = app(InsightService::class)->getAllInsights($companyId);
        $data['insights'] = $insights;

        // 👇 alerts برا الكاش
        $alerts = SystemAlert::query()
            ->where('company_id', $companyId)
            ->whereNull('resolved_at')
            ->whereNull('acknowledged_at')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($alert) => [
                'id' => $alert->id,
                'type' => $alert->type,
                'priority' => $alert->priority,
                'message' => $alert->message,
                'meta' => $alert->meta,
                'time' => $alert->triggered_at,
            ]);

        return response()->json([
            'msg' => 'ERP dashboard',
            'status' => 200,
            'data' => [
                ...$data,
                'reminders' => [
                    ...($data['reminders'] ?? []),
                    'alerts' => $alerts,
                ]
            ],
        ]);
    }
}
