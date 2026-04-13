<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemAlert;
use App\Services\InsightService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ErpDashboardController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $range = $request->get('range', 'day'); // day, week, month

        $data = Cache::remember(
            "dashboard_{$companyId}_{$range}",
            60,
            function () use ($companyId, $range) {
                $dateRange = $this->getDateRange($range);

                // Current Period
                $currentStart = $dateRange['current_start'];
                $currentEnd = $dateRange['current_end'];

                // Previous Period
                $previousStart = $dateRange['previous_start'];
                $previousEnd = $dateRange['previous_end'];

                // =================================================
                // 1. Patients
                // =================================================
                $totalPatientsCurrent = Customer::query()
                    ->where('company_id', $companyId)
                    ->whereBetween('created_at', [$currentStart, $currentEnd])
                    ->count();

                $totalPatientsPrevious = Customer::query()
                    ->where('company_id', $companyId)
                    ->whereBetween('created_at', [$previousStart, $previousEnd])
                    ->count();

                // =================================================
                // 2. Appointments
                // =================================================
                $appointmentsCurrent = Appointment::query()
                    ->where('company_id', $companyId)
                    ->whereBetween('appointment_date', [$currentStart, $currentEnd])
                    ->count();

                $appointmentsPrevious = Appointment::query()
                    ->where('company_id', $companyId)
                    ->whereBetween('appointment_date', [$previousStart, $previousEnd])
                    ->count();

                $completedCurrent = Appointment::query()
                    ->where('company_id', $companyId)
                    ->whereBetween('appointment_date', [$currentStart, $currentEnd])
                    ->where('status', 'completed')
                    ->count();

                $completedPrevious = Appointment::query()
                    ->where('company_id', $companyId)
                    ->whereBetween('appointment_date', [$previousStart, $previousEnd])
                    ->where('status', 'completed')
                    ->count();

                $cancelledCurrent = Appointment::query()
                    ->where('company_id', $companyId)
                    ->whereBetween('appointment_date', [$currentStart, $currentEnd])
                    ->where('status', 'cancelled')
                    ->count();

                $cancelledPrevious = Appointment::query()
                    ->where('company_id', $companyId)
                    ->whereBetween('appointment_date', [$previousStart, $previousEnd])
                    ->where('status', 'cancelled')
                    ->count();

                $noShowCurrent = Appointment::query()
                    ->where('company_id', $companyId)
                    ->whereBetween('appointment_date', [$currentStart, $currentEnd])
                    ->where('status', 'no_show')
                    ->count();

                $noShowPrevious = Appointment::query()
                    ->where('company_id', $companyId)
                    ->whereBetween('appointment_date', [$previousStart, $previousEnd])
                    ->where('status', 'no_show')
                    ->count();

                $recentAppointments = Appointment::query()
                    ->where('company_id', $companyId)
                    ->with(['patient:id,name,email', 'doctor:id,name'])
                    ->orderByDesc('appointment_date')
                    ->orderByDesc('appointment_time')
                    ->limit(5)
                    ->get();

                // =================================================
                // 3. Revenue
                // =================================================
                $revenueCurrent = (float) Payment::query()
                    ->where('company_id', $companyId)
                    ->whereBetween('paid_at', [$currentStart, $currentEnd])
                    ->sum('applied_amount');

                $revenuePrevious = (float) Payment::query()
                    ->where('company_id', $companyId)
                    ->whereBetween('paid_at', [$previousStart, $previousEnd])
                    ->sum('applied_amount');

                $recentPayments = Payment::query()
                    ->where('company_id', $companyId)
                    ->latest()
                    ->limit(5)
                    ->get();

                // =================================================
                // 4. Invoices
                // =================================================
                $unpaidCurrent = Invoice::query()
                    ->where('company_id', $companyId)
                    ->where('status', 'unpaid')
                    ->whereBetween('issued_at', [$currentStart, $currentEnd])
                    ->count();

                $unpaidPrevious = Invoice::query()
                    ->where('company_id', $companyId)
                    ->where('status', 'unpaid')
                    ->whereBetween('issued_at', [$previousStart, $previousEnd])
                    ->count();

                $paidCurrent = Invoice::query()
                    ->where('company_id', $companyId)
                    ->where('status', 'paid')
                    ->whereBetween('issued_at', [$currentStart, $currentEnd])
                    ->count();

                $paidPrevious = Invoice::query()
                    ->where('company_id', $companyId)
                    ->where('status', 'paid')
                    ->whereBetween('issued_at', [$previousStart, $previousEnd])
                    ->count();

                $recentInvoices = Invoice::query()
                    ->where('company_id', $companyId)
                    ->latest()
                    ->limit(5)
                    ->get();

                // =================================================
                // KPIs with Comparison
                // =================================================
                $kpis = [
                    'revenue' => [
                        'current' => $revenueCurrent,
                        'previous' => $revenuePrevious,
                        'delta' => $this->calculateDelta($revenueCurrent, $revenuePrevious),
                    ],
                    'appointments' => [
                        'current' => $appointmentsCurrent,
                        'previous' => $appointmentsPrevious,
                        'delta' => $this->calculateDelta($appointmentsCurrent, $appointmentsPrevious),
                    ],
                    'completed_appointments' => [
                        'current' => $completedCurrent,
                        'previous' => $completedPrevious,
                        'delta' => $this->calculateDelta($completedCurrent, $completedPrevious),
                    ],
                    'cancelled_appointments' => [
                        'current' => $cancelledCurrent,
                        'previous' => $cancelledPrevious,
                        'delta' => $this->calculateDelta($cancelledCurrent, $cancelledPrevious),
                    ],
                    'no_show_appointments' => [
                        'current' => $noShowCurrent,
                        'previous' => $noShowPrevious,
                        'delta' => $this->calculateDelta($noShowCurrent, $noShowPrevious),
                    ],
                    'unpaid_invoices' => [
                        'current' => $unpaidCurrent,
                        'previous' => $unpaidPrevious,
                        'delta' => $this->calculateDelta($unpaidCurrent, $unpaidPrevious),
                    ],
                    'paid_invoices' => [
                        'current' => $paidCurrent,
                        'previous' => $paidPrevious,
                        'delta' => $this->calculateDelta($paidCurrent, $paidPrevious),
                    ],
                    'total_patients' => [
                        'current' => $totalPatientsCurrent,
                        'previous' => $totalPatientsPrevious,
                        'delta' => $this->calculateDelta($totalPatientsCurrent, $totalPatientsPrevious),
                    ],
                ];

                // =================================================
                // Charts Data
                // =================================================
                $revenueChartData = $this->getRevenueChartData($companyId, $range);
                $appointmentsChartData = $this->getAppointmentsChartData($companyId, $range);

                return [
                    'kpis' => $kpis,
                    'recent_appointments' => $recentAppointments,
                    'recent_invoices' => $recentInvoices,
                    'recent_payments' => $recentPayments,
                    'charts' => [
                        'revenue' => $revenueChartData,
                        'appointments' => $appointmentsChartData,
                    ],
                    'range' => $range,
                ];
            }
        );

        $insights = app(InsightService::class)->getAllInsights($companyId);
        $data['insights'] = $insights;

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
                'reminders' => ['alerts' => $alerts],
            ],
        ]);
    }

    /**
     * Get date range based on selected range
     */
    private function getDateRange($range): array
    {
        $now = Carbon::now();
        $yesterday = Carbon::yesterday();
        $lastWeek = Carbon::now()->subWeek();
        $lastMonth = Carbon::now()->subMonth();

        return match ($range) {
            'week' => [
                'current_start' => $now->copy()->startOfWeek(),
                'current_end' => $now->copy()->endOfWeek(),
                'previous_start' => $lastWeek->copy()->startOfWeek(),
                'previous_end' => $lastWeek->copy()->endOfWeek(),
            ],
            'month' => [
                'current_start' => $now->copy()->startOfMonth(),
                'current_end' => $now->copy()->endOfMonth(),
                'previous_start' => $lastMonth->copy()->startOfMonth(),
                'previous_end' => $lastMonth->copy()->endOfMonth(),
            ],
            default => [ // day
                'current_start' => $now->copy()->startOfDay(),
                'current_end' => $now->copy()->endOfDay(),
                'previous_start' => $yesterday->copy()->startOfDay(),
                'previous_end' => $yesterday->copy()->endOfDay(),
            ],
        };
    }

    /**
     * Calculate delta percentage
     */
    private function calculateDelta($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Get revenue chart data
     */
    private function getRevenueChartData($companyId, $range): array
    {
        $dateRange = $this->getDateRange($range);
        $start = $dateRange['current_start'];
        $end = $dateRange['current_end'];

        $data = [];
        $current = $start->copy();

        while ($current <= $end) {
            $revenue = (float) Payment::query()
                ->where('company_id', $companyId)
                ->whereDate('paid_at', $current)
                ->sum('applied_amount');

            $data[] = [
                'date' => $current->toDateString(),
                'value' => $revenue,
                'label' => $current->format($range === 'month' ? 'M d' : 'D'),
            ];

            $current->addDay();
        }
        return $data;
    }

    /**
     * Get appointments chart data
     */
    private function getAppointmentsChartData($companyId, $range): array
    {
        $dateRange = $this->getDateRange($range);
        $start = $dateRange['current_start'];
        $end = $dateRange['current_end'];

        $data = [];
        $current = $start->copy();

        while ($current <= $end) {
            $total = Appointment::query()
                ->where('company_id', $companyId)
                ->whereDate('appointment_date', $current)
                ->count();

            $completed = Appointment::query()
                ->where('company_id', $companyId)
                ->whereDate('appointment_date', $current)
                ->where('status', 'completed')
                ->count();

            $cancelled = Appointment::query()
                ->where('company_id', $companyId)
                ->whereDate('appointment_date', $current)
                ->where('status', 'cancelled')
                ->count();

            $data[] = [
                'date' => $current->toDateString(),
                'label' => $current->format($range === 'month' ? 'M d' : 'D'),
                'total' => $total,
                'completed' => $completed,
                'cancelled' => $cancelled,
            ];

            $current->addDay();
        }
        return $data;
    }
}
