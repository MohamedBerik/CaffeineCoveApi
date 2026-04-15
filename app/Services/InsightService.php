<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Invoice;
use App\Services\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InsightService
{
    /**
     * Get all insights for current company
     */
    public function getAllInsights(?int $companyId = null): array
    {
        $companyId = $companyId ?? Tenant::id();

        if (!$companyId) {
            return [];
        }

        $insights = array_filter([
            $this->revenueInsight($companyId),
            $this->missedAppointmentsInsight($companyId),
            $this->unpaidInvoicesInsight($companyId),
            $this->revenueGrowthTrendInsight($companyId),
            $this->revenueForecastInsight($companyId),
            $this->topDoctorInsight($companyId),
            $this->highCancellationRateInsight($companyId),
            ...$this->getNoShowInsights($companyId),
            ...$this->getPatientInsights($companyId),
        ]);

        return array_values($insights);
    }

    /**
     * Generate revenue insight based on day-over-day change.
     */
    public function revenueInsight(int $companyId): ?array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // ✅ بدون where('company_id') - الـ Scope هيضيفه
        $todayRevenue = (float) Payment::query()
            ->whereDate('paid_at', $today)
            ->sum('applied_amount');

        $yesterdayRevenue = (float) Payment::query()
            ->whereDate('paid_at', $yesterday)
            ->sum('applied_amount');

        if ($yesterdayRevenue <= 0) {
            return null;
        }

        $change = (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100;

        if ($change < -20) {
            // ✅ بدون where('company_id')
            $cancelledToday = Appointment::query()
                ->whereDate('appointment_date', $today)
                ->where('status', 'cancelled')
                ->count();

            $noShowToday = Appointment::query()
                ->whereDate('appointment_date', $today)
                ->where('status', 'no_show')
                ->count();

            return [
                'type' => 'insight',
                'category' => 'revenue',
                'priority' => 'high',
                'message' => "Revenue dropped by " . round(abs($change)) . "%",
                'point' => [
                    'date' => $today->toDateString(),
                    'value' => $todayRevenue,
                ],
                'action' => [
                    'type' => 'navigate',
                    'url' => '/admin/erp/reports?type=revenue&period=week',
                    'label' => 'Analyze revenue drop'
                ],
                'explanation' => [
                    'summary' => 'Revenue drop causes',
                    'factors' => [
                        ['label' => 'Today\'s Revenue', 'value' => number_format($todayRevenue) . ' EGP'],
                        ['label' => 'Yesterday\'s Revenue', 'value' => number_format($yesterdayRevenue) . ' EGP'],
                        ['label' => 'Change', 'value' => round($change) . '%'],
                        ['label' => 'Cancelled Appointments', 'value' => $cancelledToday],
                        ['label' => 'No-Show Appointments', 'value' => $noShowToday],
                    ]
                ],
                'meta' => [
                    'today_revenue' => $todayRevenue,
                    'yesterday_revenue' => $yesterdayRevenue,
                    'change_percent' => round($change, 2),
                ],
            ];
        }

        return null;
    }

    /**
     * Generate missed appointments insight based on day-over-day change.
     */
    public function missedAppointmentsInsight(int $companyId): ?array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // ✅ بدون where('company_id')
        $todayMissed = Appointment::query()
            ->whereDate('appointment_date', $today)
            ->where('status', 'no_show')
            ->count();

        $yesterdayMissed = Appointment::query()
            ->whereDate('appointment_date', $yesterday)
            ->where('status', 'no_show')
            ->count();

        if ($yesterdayMissed <= 0) {
            return null;
        }

        $change = (($todayMissed - $yesterdayMissed) / $yesterdayMissed) * 100;

        if ($change > 30) {
            // ✅ بدون where('company_id')
            $weekMissed = Appointment::query()
                ->whereBetween('appointment_date', [Carbon::today()->subDays(7), $today])
                ->where('status', 'no_show')
                ->count();

            return [
                'type' => 'insight',
                'category' => 'appointments',
                'priority' => 'medium',
                'message' => "Missed appointments increased by " . round($change) . "%",
                'point' => [
                    'date' => $today->toDateString(),
                    'value' => $todayMissed,
                ],
                'action' => [
                    'type' => 'navigate',
                    'url' => '/admin/erp/appointments?status=no_show',
                    'label' => 'View no-show appointments'
                ],
                'explanation' => [
                    'summary' => 'No-show appointments breakdown',
                    'factors' => [
                        ['label' => 'Today\'s No-Shows', 'value' => $todayMissed],
                        ['label' => 'Yesterday\'s No-Shows', 'value' => $yesterdayMissed],
                        ['label' => 'Increase', 'value' => round($change) . '%'],
                        ['label' => 'Last 7 Days Total', 'value' => $weekMissed],
                    ]
                ],
                'meta' => [
                    'today_missed' => $todayMissed,
                    'yesterday_missed' => $yesterdayMissed,
                    'change_percent' => round($change, 2),
                ],
            ];
        }

        return null;
    }

    /**
     * Generate unpaid invoices insight.
     */
    public function unpaidInvoicesInsight(int $companyId): ?array
    {
        // ✅ بدون where('company_id')
        $unpaidCount = Invoice::query()
            ->where('status', 'unpaid')
            ->count();

        $overdueCount = Invoice::query()
            ->where('status', 'unpaid')
            ->where('issued_at', '<', Carbon::now()->subDays(30))
            ->count();

        if ($unpaidCount > 10 && $overdueCount > 5) {
            return [
                'type' => 'insight',
                'category' => 'invoices',
                'priority' => 'medium',
                'message' => "You have {$unpaidCount} unpaid invoices ({$overdueCount} overdue)",
                'point' => [
                    'date' => Carbon::today()->toDateString(),
                    'value' => $unpaidCount,
                ],
                'action' => [
                    'type' => 'navigate',
                    'url' => '/admin/erp/invoices?status=unpaid',
                    'label' => 'View unpaid invoices'
                ],
                'explanation' => [
                    'summary' => 'Unpaid invoices breakdown',
                    'factors' => [
                        ['label' => 'Total Unpaid', 'value' => $unpaidCount],
                        ['label' => 'Overdue (>30 days)', 'value' => $overdueCount],
                        ['label' => 'Overdue (>60 days)', 'value' => Invoice::query()
                            ->where('status', 'unpaid')
                            ->whereDate('issued_at', '<=', Carbon::now()->subDays(60))
                            ->count()],
                    ]
                ],
                'meta' => [
                    'unpaid_count' => $unpaidCount,
                    'overdue_count' => $overdueCount,
                ],
            ];
        }

        return null;
    }

    /**
     * Detect revenue growing trend (3 consecutive days of growth)
     */
    public function revenueGrowthTrendInsight(int $companyId): ?array
    {
        $dates = [];
        $revenues = [];

        for ($i = 0; $i < 4; $i++) {
            $date = Carbon::today()->subDays($i);
            $dates[] = $date->toDateString();
            // ✅ بدون where('company_id')
            $revenues[] = (float) Payment::query()
                ->whereDate('paid_at', $date)
                ->sum('applied_amount');
        }

        $revenues = array_reverse($revenues);
        $dates = array_reverse($dates);

        $growing = true;
        for ($i = 1; $i < 3; $i++) {
            if ($revenues[$i] <= $revenues[$i - 1]) {
                $growing = false;
                break;
            }
        }

        if ($growing && $revenues[2] > 0) {
            $growthPercent = (($revenues[2] - $revenues[0]) / $revenues[0]) * 100;

            return [
                'type' => 'insight',
                'category' => 'revenue',
                'priority' => 'low',
                'message' => "Revenue growing for 3 consecutive days (+" . round($growthPercent) . "%)",
                'point' => [
                    'date' => $dates[2],
                    'value' => $revenues[2],
                ],
                'action' => [
                    'type' => 'navigate',
                    'url' => '/admin/erp/reports?type=revenue&trend=growth',
                    'label' => 'View revenue trend'
                ],
                'explanation' => [
                    'summary' => 'Revenue growth trend',
                    'factors' => [
                        ['label' => 'Day 1', 'value' => number_format($revenues[0]) . ' EGP'],
                        ['label' => 'Day 2', 'value' => number_format($revenues[1]) . ' EGP'],
                        ['label' => 'Day 3 (Today)', 'value' => number_format($revenues[2]) . ' EGP'],
                        ['label' => 'Total Growth', 'value' => '+' . round($growthPercent) . '%'],
                    ]
                ],
                'meta' => [
                    'trend' => 'growing',
                    'days' => 3,
                    'growth_percent' => round($growthPercent, 2),
                    'revenues' => $revenues,
                ],
            ];
        }

        return null;
    }

    /**
     * Forecast today's revenue based on historical average
     */
    public function revenueForecastInsight(int $companyId): ?array
    {
        // ✅ بدون where('company_id')
        $avgRevenue = (float) Payment::query()
            ->whereDate('paid_at', '>=', Carbon::today()->subDays(7))
            ->whereDate('paid_at', '<', Carbon::today())
            ->sum('applied_amount') / 7;

        $todayRevenue = (float) Payment::query()
            ->whereDate('paid_at', Carbon::today())
            ->sum('applied_amount');

        $expectedToday = max($avgRevenue, $todayRevenue);

        $maxDayRevenue = Payment::query()
            ->whereDate('paid_at', '>=', Carbon::today()->subDays(7))
            ->whereDate('paid_at', '<', Carbon::today())
            ->selectRaw('DATE(paid_at) as date, SUM(applied_amount) as total')
            ->groupBy('date')
            ->orderByDesc('total')
            ->first();

        if ($expectedToday > 1000) {
            return [
                'type' => 'insight',
                'category' => 'revenue',
                'priority' => 'low',
                'message' => "Expected revenue today: " . number_format($expectedToday) . " EGP",
                'point' => [
                    'date' => Carbon::today()->toDateString(),
                    'value' => $expectedToday,
                ],
                'action' => [
                    'type' => 'navigate',
                    'url' => '/admin/erp/reports?type=revenue&view=forecast',
                    'label' => 'View forecast details'
                ],
                'explanation' => [
                    'summary' => 'Revenue forecast based on last 7 days',
                    'factors' => [
                        ['label' => '7-Day Average', 'value' => number_format($avgRevenue) . ' EGP'],
                        ['label' => 'Current Revenue', 'value' => number_format($todayRevenue) . ' EGP'],
                        ['label' => 'Expected Today', 'value' => number_format($expectedToday) . ' EGP'],
                        ['label' => 'Best Day (Last Week)', 'value' => $maxDayRevenue ? number_format($maxDayRevenue->total) . ' EGP' : 'N/A'],
                    ]
                ],
                'meta' => [
                    'forecast' => $expectedToday,
                    'current' => $todayRevenue,
                    'average_7days' => round($avgRevenue, 2),
                ],
            ];
        }

        return null;
    }

    /**
     * Highlight top performing doctor today
     */
    public function topDoctorInsight(int $companyId): ?array
    {
        $today = Carbon::today();

        // ✅ بدون where('company_id')
        $topDoctor = Appointment::query()
            ->whereDate('appointment_date', $today)
            ->where('status', 'completed')
            ->select('doctor_id', 'doctor_name', DB::raw('COUNT(*) as completed_count'))
            ->groupBy('doctor_id', 'doctor_name')
            ->orderByDesc('completed_count')
            ->first();

        if ($topDoctor && $topDoctor->completed_count >= 3) {
            $totalAppointments = Appointment::query()
                ->whereDate('appointment_date', $today)
                ->where('doctor_id', $topDoctor->doctor_id)
                ->count();

            $completionRate = ($topDoctor->completed_count / $totalAppointments) * 100;

            return [
                'type' => 'insight',
                'category' => 'doctors',
                'priority' => 'low',
                'message' => "Dr. {$topDoctor->doctor_name} has highest completion rate today ({$topDoctor->completed_count} appointments)",
                'point' => [
                    'date' => $today->toDateString(),
                    'value' => $topDoctor->completed_count,
                ],
                'action' => [
                    'type' => 'navigate',
                    'url' => "/admin/erp/doctors/{$topDoctor->doctor_id}/performance",
                    'label' => 'View doctor performance'
                ],
                'explanation' => [
                    'summary' => 'Doctor performance breakdown',
                    'factors' => [
                        ['label' => 'Doctor Name', 'value' => $topDoctor->doctor_name],
                        ['label' => 'Completed Appointments', 'value' => $topDoctor->completed_count],
                        ['label' => 'Total Appointments', 'value' => $totalAppointments],
                        ['label' => 'Completion Rate', 'value' => round($completionRate) . '%'],
                    ]
                ],
                'meta' => [
                    'doctor_id' => $topDoctor->doctor_id,
                    'doctor_name' => $topDoctor->doctor_name,
                    'completed_count' => $topDoctor->completed_count,
                ],
            ];
        }

        return null;
    }

    /**
     * Alert on high cancellation rate today
     */
    public function highCancellationRateInsight(int $companyId): ?array
    {
        $today = Carbon::today();

        // ✅ بدون where('company_id')
        $totalToday = Appointment::query()
            ->whereDate('appointment_date', $today)
            ->count();

        $cancelledToday = Appointment::query()
            ->whereDate('appointment_date', $today)
            ->where('status', 'cancelled')
            ->count();

        if ($totalToday >= 5) {
            $cancellationRate = ($cancelledToday / $totalToday) * 100;

            if ($cancellationRate > 30) {
                $topCancellingDoctor = Appointment::query()
                    ->whereDate('appointment_date', $today)
                    ->where('status', 'cancelled')
                    ->select('doctor_name', DB::raw('COUNT(*) as cancelled_count'))
                    ->groupBy('doctor_name')
                    ->orderByDesc('cancelled_count')
                    ->first();

                return [
                    'type' => 'insight',
                    'category' => 'appointments',
                    'priority' => 'medium',
                    'message' => "High cancellation rate today: " . round($cancellationRate) . "% ({$cancelledToday}/{$totalToday})",
                    'point' => [
                        'date' => $today->toDateString(),
                        'value' => $cancelledToday,
                    ],
                    'action' => [
                        'type' => 'navigate',
                        'url' => '/admin/erp/appointments?status=cancelled',
                        'label' => 'View cancelled appointments'
                    ],
                    'explanation' => [
                        'summary' => 'Cancellation breakdown',
                        'factors' => [
                            ['label' => 'Total Appointments', 'value' => $totalToday],
                            ['label' => 'Cancelled', 'value' => $cancelledToday],
                            ['label' => 'Cancellation Rate', 'value' => round($cancellationRate) . '%'],
                            ['label' => 'Highest Cancelling Doctor', 'value' => $topCancellingDoctor ? $topCancellingDoctor->doctor_name . ' (' . $topCancellingDoctor->cancelled_count . ')' : 'N/A'],
                        ]
                    ],
                    'meta' => [
                        'cancellation_rate' => round($cancellationRate, 2),
                        'cancelled' => $cancelledToday,
                        'total' => $totalToday,
                    ],
                ];
            }
        }

        return null;
    }

    private function getNoShowInsights(int $companyId): array
    {
        $insights = [];
        $monthStart = Carbon::now()->startOfMonth();

        // ✅ بدون where('company_id')
        $noShows = Appointment::query()
            ->whereBetween('appointment_date', [$monthStart, Carbon::now()])
            ->where('status', 'no_show')
            ->count();

        $topNoShowDoctor = Appointment::query()
            ->whereBetween('appointment_date', [$monthStart, Carbon::now()])
            ->where('status', 'no_show')
            ->select('doctor_name', DB::raw('COUNT(*) as no_show_count'))
            ->groupBy('doctor_name')
            ->orderByDesc('no_show_count')
            ->first();

        if ($noShows > 3) {
            $insights[] = [
                'type' => 'insight',
                'category' => 'appointments',
                'priority' => 'high',
                'message' => "High no-show rate: {$noShows} patients didn't show up this month",
                'action' => [
                    'type' => 'navigate',
                    'url' => '/admin/erp/appointments?status=no_show',
                    'label' => 'View no-show appointments'
                ],
                'explanation' => [
                    'summary' => 'No-show appointments this month',
                    'factors' => [
                        ['label' => 'Total No-Shows', 'value' => $noShows],
                        ['label' => 'Most Affected Doctor', 'value' => $topNoShowDoctor ? $topNoShowDoctor->doctor_name . ' (' . $topNoShowDoctor->no_show_count . ')' : 'N/A'],
                    ]
                ],
                'point' => [
                    'date' => Carbon::today()->toDateString(),
                    'value' => $noShows,
                ],
            ];
        }

        return $insights;
    }

    private function getPatientInsights(int $companyId): array
    {
        $insights = [];
        $lastMonth = Carbon::now()->subMonth();

        // ✅ بدون where('company_id')
        $newPatients = Customer::query()
            ->whereBetween('created_at', [$lastMonth, Carbon::now()])
            ->count();

        $totalPatients = Customer::query()->count();

        if ($newPatients > 10) {
            $insights[] = [
                'type' => 'insight',
                'category' => 'patients',
                'priority' => 'positive',
                'message' => "Great! {$newPatients} new patients joined this month",
                'action' => [
                    'type' => 'navigate',
                    'url' => '/admin/erp/patients?period=month',
                    'label' => 'View new patients'
                ],
                'explanation' => [
                    'summary' => 'Patient growth this month',
                    'factors' => [
                        ['label' => 'New Patients', 'value' => $newPatients],
                        ['label' => 'Total Patients', 'value' => $totalPatients],
                        ['label' => 'Growth Rate', 'value' => $totalPatients > 0 ? round(($newPatients / $totalPatients) * 100) . '%' : '100%'],
                    ]
                ],
            ];
        }

        return $insights;
    }

    /**
     * ✅ Generate insights for all companies (Super Admin only)
     */
    public function getAllCompaniesInsights(): array
    {
        if (!Tenant::isSuperAdmin()) {
            return [];
        }

        return Tenant::asSuperAdmin(function () {
            $companies = \App\Models\Company::query()
                ->whereIn('status', ['active', 'trial'])
                ->get();

            $allInsights = [];

            foreach ($companies as $company) {
                $companyInsights = $this->getAllInsights($company->id);

                if (!empty($companyInsights)) {
                    $allInsights[] = [
                        'company_id' => $company->id,
                        'company_name' => $company->name,
                        'insights' => $companyInsights,
                    ];
                }
            }

            return $allInsights;
        });
    }
}
