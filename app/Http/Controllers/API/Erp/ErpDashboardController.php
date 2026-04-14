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
        $compare = $request->get('compare', false); // true/false

        $cacheKey = "dashboard_{$companyId}_{$range}_" . ($compare ? 'compare' : 'normal');

        $data = Cache::remember(
            $cacheKey,
            60,
            function () use ($companyId, $range, $compare) {

                // =================================================
                // 1. Get Date Ranges for Current & Previous
                // =================================================
                $dateRanges = $this->getDateRanges($range);

                // =================================================
                // 2. Charts Data with Comparison Support
                // =================================================
                $revenueChart = $this->getRevenueChartWithComparison(
                    $companyId,
                    $dateRanges,
                    $compare
                );

                $appointmentsChart = $this->getAppointmentsChartWithComparison(
                    $companyId,
                    $dateRanges,
                    $compare
                );

                // =================================================
                // 3. KPIs with Delta Calculation
                // =================================================
                $kpis = $this->getKpisWithComparison(
                    $companyId,
                    $dateRanges,
                    $compare
                );

                // =================================================
                // 4. Recent Data (No comparison needed)
                // =================================================
                $recentAppointments = Appointment::query()
                    ->where('company_id', $companyId)
                    ->with(['patient:id,name,email', 'doctor:id,name'])
                    ->orderByDesc('appointment_date')
                    ->orderByDesc('appointment_time')
                    ->limit(5)
                    ->get();

                $recentPayments = Payment::query()
                    ->where('company_id', $companyId)
                    ->latest()
                    ->limit(5)
                    ->get();

                $recentInvoices = Invoice::query()
                    ->where('company_id', $companyId)
                    ->latest()
                    ->limit(5)
                    ->get();

                return [
                    'charts' => [
                        'revenue' => $revenueChart,
                        'appointments' => $appointmentsChart,
                    ],
                    'kpis' => $kpis,
                    'recent_appointments' => $recentAppointments,
                    'recent_invoices' => $recentInvoices,
                    'recent_payments' => $recentPayments,
                    'range' => $range,
                    'comparison' => [
                        'enabled' => $compare,
                        'type' => $compare ? 'previous_period' : null,
                    ],
                ];
            }
        );

        // Insights & Alerts (Not cached or separate cache)
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
     * Get date ranges for current and previous periods
     */
    private function getDateRanges(string $range): array
    {
        $now = Carbon::now();

        return match ($range) {
            'week' => [
                'current' => [
                    'start' => $now->copy()->startOfWeek(),
                    'end' => $now->copy()->endOfWeek(),
                ],
                'previous' => [
                    'start' => $now->copy()->subWeek()->startOfWeek(),
                    'end' => $now->copy()->subWeek()->endOfWeek(),
                ],
            ],
            'month' => [
                'current' => [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                ],
                'previous' => [
                    'start' => $now->copy()->subMonth()->startOfMonth(),
                    'end' => $now->copy()->subMonth()->endOfMonth(),
                ],
            ],
            default => [ // day
                'current' => [
                    'start' => $now->copy()->startOfDay(),
                    'end' => $now->copy()->endOfDay(),
                ],
                'previous' => [
                    'start' => $now->copy()->subDay()->startOfDay(),
                    'end' => $now->copy()->subDay()->endOfDay(),
                ],
            ],
        };
    }

    /**
     * Get revenue chart with comparison (normalized)
     */
    private function getRevenueChartWithComparison($companyId, array $dateRanges, bool $compare): array
    {
        // Get current period data
        $currentData = $this->getRevenueSeries(
            $companyId,
            $dateRanges['current']['start'],
            $dateRanges['current']['end'],
            $dateRanges
        );

        if (!$compare) {
            return $currentData;
        }

        // Get previous period data
        $previousData = $this->getRevenueSeries(
            $companyId,
            $dateRanges['previous']['start'],
            $dateRanges['previous']['end'],
            $dateRanges
        );

        // Normalize both series to same length
        return $this->normalizeSeries($currentData, $previousData);
    }

    /**
     * Get appointments chart with comparison (normalized)
     */
    private function getAppointmentsChartWithComparison($companyId, array $dateRanges, bool $compare): array
    {
        // Get current period data with status breakdown
        $currentData = $this->getAppointmentsSeriesWithStatus(
            $companyId,
            $dateRanges['current']['start'],
            $dateRanges['current']['end'],
            $dateRanges
        );

        if (!$compare) {
            return $currentData;
        }

        $previousData = $this->getAppointmentsSeriesWithStatus(
            $companyId,
            $dateRanges['previous']['start'],
            $dateRanges['previous']['end'],
            $dateRanges
        );

        return $this->normalizeSeries($currentData, $previousData);
    }


    /**
     * Get revenue time series data
     */
    private function getRevenueSeries($companyId, Carbon $start, Carbon $end, array $dateRanges): array
    {
        $data = [];
        $current = $start->copy();

        while ($current <= $end) {
            $revenue = (float) Payment::query()
                ->where('company_id', $companyId)
                ->whereDate('paid_at', $current)
                ->sum('applied_amount');

            $data[] = [
                'label' => $this->getLabelForDate($current, $dateRanges),
                'value' => $revenue,
                'date' => $current->toDateString(),
            ];

            $current->addDay();
        }

        return $data;
    }

    /**
     * Get appointments time series data
     */
    private function getAppointmentsSeriesWithStatus($companyId, Carbon $start, Carbon $end, array $dateRanges): array
    {
        $data = [];
        $current = $start->copy();

        while ($current <= $end) {
            // Total appointments
            $total = Appointment::query()
                ->where('company_id', $companyId)
                ->whereDate('appointment_date', $current)
                ->count();

            // Completed appointments
            $completed = Appointment::query()
                ->where('company_id', $companyId)
                ->whereDate('appointment_date', $current)
                ->where('status', 'completed')
                ->count();

            // Cancelled appointments
            $cancelled = Appointment::query()
                ->where('company_id', $companyId)
                ->whereDate('appointment_date', $current)
                ->where('status', 'cancelled')
                ->count();

            $data[] = [
                'label' => $this->getLabelForDate($current, $dateRanges),
                'value' => $total,
                'completed' => $completed,
                'cancelled' => $cancelled,
                'date' => $current->toDateString(),
            ];

            $current->addDay();
        }

        return $data;
    }

    /**
     * Get label for date based on range
     */
    private function getLabelForDate(Carbon $date, array $dateRanges): string
    {
        $rangeType = $this->detectRangeType($dateRanges);

        return match ($rangeType) {
            'month' => $date->format('M d'),
            'week' => $date->format('D'),
            default => $date->format('D'),
        };
    }

    /**
     * Detect range type from date ranges
     */
    private function detectRangeType(array $dateRanges): string
    {
        $daysDiff = $dateRanges['current']['start']->diffInDays($dateRanges['current']['end']);

        if ($daysDiff > 28) return 'month';
        if ($daysDiff > 1) return 'week';
        return 'day';
    }

    /**
     * Normalize two series to same length (VERY IMPORTANT)
     */
    private function normalizeSeries(array $current, array $previous): array
    {
        $maxLength = max(count($current), count($previous));
        $normalized = [];

        for ($i = 0; $i < $maxLength; $i++) {
            $currentItem = $current[$i] ?? [];
            $previousItem = $previous[$i] ?? [];

            $normalized[] = [
                'label' => $currentItem['label'] ?? $previousItem['label'] ?? "Item {$i}",
                'current' => $currentItem['value'] ?? 0,
                'previous' => $previousItem['value'] ?? 0,
                // ✅ حفظ completed و cancelled لو موجودين
                'completed' => $currentItem['completed'] ?? $previousItem['completed'] ?? 0,
                'cancelled' => $currentItem['cancelled'] ?? $previousItem['cancelled'] ?? 0,
                'date' => $currentItem['date'] ?? $previousItem['date'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * Get KPIs with comparison and delta calculation
     */
    private function getKpisWithComparison($companyId, array $dateRanges, bool $compare): array
    {
        // Current period totals
        $currentRevenue = $this->sumRevenue($companyId, $dateRanges['current']['start'], $dateRanges['current']['end']);
        $currentAppointments = $this->countAppointments($companyId, $dateRanges['current']['start'], $dateRanges['current']['end']);
        $currentCompleted = $this->countAppointmentsByStatus($companyId, $dateRanges['current']['start'], $dateRanges['current']['end'], 'completed');
        $currentCancelled = $this->countAppointmentsByStatus($companyId, $dateRanges['current']['start'], $dateRanges['current']['end'], 'cancelled');
        $currentNoShow = $this->countAppointmentsByStatus($companyId, $dateRanges['current']['start'], $dateRanges['current']['end'], 'no_show');
        $currentPatients = $this->countCustomers($companyId, $dateRanges['current']['start'], $dateRanges['current']['end']);
        $currentUnpaidInvoices = $this->countInvoicesByStatus($companyId, $dateRanges['current']['start'], $dateRanges['current']['end'], 'unpaid');
        $currentPaidInvoices = $this->countInvoicesByStatus($companyId, $dateRanges['current']['start'], $dateRanges['current']['end'], 'paid');

        $kpis = [
            'revenue' => [
                'current' => $currentRevenue,
                'previous' => null,
                'delta' => null,
            ],
            'appointments' => [
                'current' => $currentAppointments,
                'previous' => null,
                'delta' => null,
            ],
            'completed_appointments' => [
                'current' => $currentCompleted,
                'previous' => null,
                'delta' => null,
            ],
            'cancelled_appointments' => [
                'current' => $currentCancelled,
                'previous' => null,
                'delta' => null,
            ],
            'no_show_appointments' => [
                'current' => $currentNoShow,
                'previous' => null,
                'delta' => null,
            ],
            'total_patients' => [
                'current' => $currentPatients,
                'previous' => null,
                'delta' => null,
            ],
            'unpaid_invoices' => [
                'current' => $currentUnpaidInvoices,
                'previous' => null,
                'delta' => null,
            ],
            'paid_invoices' => [
                'current' => $currentPaidInvoices,
                'previous' => null,
                'delta' => null,
            ],
        ];

        if ($compare) {
            // Previous period totals
            $previousRevenue = $this->sumRevenue($companyId, $dateRanges['previous']['start'], $dateRanges['previous']['end']);
            $previousAppointments = $this->countAppointments($companyId, $dateRanges['previous']['start'], $dateRanges['previous']['end']);
            $previousCompleted = $this->countAppointmentsByStatus($companyId, $dateRanges['previous']['start'], $dateRanges['previous']['end'], 'completed');
            $previousCancelled = $this->countAppointmentsByStatus($companyId, $dateRanges['previous']['start'], $dateRanges['previous']['end'], 'cancelled');
            $previousNoShow = $this->countAppointmentsByStatus($companyId, $dateRanges['previous']['start'], $dateRanges['previous']['end'], 'no_show');
            $previousPatients = $this->countCustomers($companyId, $dateRanges['previous']['start'], $dateRanges['previous']['end']);
            $previousUnpaidInvoices = $this->countInvoicesByStatus($companyId, $dateRanges['previous']['start'], $dateRanges['previous']['end'], 'unpaid');
            $previousPaidInvoices = $this->countInvoicesByStatus($companyId, $dateRanges['previous']['start'], $dateRanges['previous']['end'], 'paid');

            $kpis = [
                'revenue' => [
                    'current' => $currentRevenue,
                    'previous' => $previousRevenue,
                    'delta' => $this->calculateDelta($currentRevenue, $previousRevenue),
                ],
                'appointments' => [
                    'current' => $currentAppointments,
                    'previous' => $previousAppointments,
                    'delta' => $this->calculateDelta($currentAppointments, $previousAppointments),
                ],
                'completed_appointments' => [
                    'current' => $currentCompleted,
                    'previous' => $previousCompleted,
                    'delta' => $this->calculateDelta($currentCompleted, $previousCompleted),
                ],
                'cancelled_appointments' => [
                    'current' => $currentCancelled,
                    'previous' => $previousCancelled,
                    'delta' => $this->calculateDelta($currentCancelled, $previousCancelled),
                ],
                'no_show_appointments' => [
                    'current' => $currentNoShow,
                    'previous' => $previousNoShow,
                    'delta' => $this->calculateDelta($currentNoShow, $previousNoShow),
                ],
                'total_patients' => [
                    'current' => $currentPatients,
                    'previous' => $previousPatients,
                    'delta' => $this->calculateDelta($currentPatients, $previousPatients),
                ],
                'unpaid_invoices' => [
                    'current' => $currentUnpaidInvoices,
                    'previous' => $previousUnpaidInvoices,
                    'delta' => $this->calculateDelta($currentUnpaidInvoices, $previousUnpaidInvoices),
                ],
                'paid_invoices' => [
                    'current' => $currentPaidInvoices,
                    'previous' => $previousPaidInvoices,
                    'delta' => $this->calculateDelta($currentPaidInvoices, $previousPaidInvoices),
                ],
            ];
        }

        return $kpis;
    }

    /**
     * Sum revenue for a date range
     */
    private function sumRevenue($companyId, Carbon $start, Carbon $end): float
    {
        return (float) Payment::query()
            ->where('company_id', $companyId)
            ->whereBetween('paid_at', [$start, $end])
            ->sum('applied_amount');
    }

    /**
     * Count appointments for a date range
     */
    private function countAppointments($companyId, Carbon $start, Carbon $end): int
    {
        return Appointment::query()
            ->where('company_id', $companyId)
            ->whereBetween('appointment_date', [$start, $end])
            ->count();
    }

    /**
     * Count appointments by status
     */
    private function countAppointmentsByStatus($companyId, Carbon $start, Carbon $end, string $status): int
    {
        return Appointment::query()
            ->where('company_id', $companyId)
            ->whereBetween('appointment_date', [$start, $end])
            ->where('status', $status)
            ->count();
    }

    /**
     * Count customers (patients)
     */
    private function countCustomers($companyId, Carbon $start, Carbon $end): int
    {
        return Customer::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /**
     * Count invoices by status
     */
    private function countInvoicesByStatus($companyId, Carbon $start, Carbon $end, string $status): int
    {
        return Invoice::query()
            ->where('company_id', $companyId)
            ->where('status', $status)
            ->whereBetween('issued_at', [$start, $end])
            ->count();
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
}
