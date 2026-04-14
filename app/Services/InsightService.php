<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InsightService
{
    /**
     * Get all insights for a company
     */
    public function getAllInsights($companyId): array
    {
        $insights = [];

        // Revenue Insights
        $insights = array_merge($insights, $this->getRevenueInsights($companyId));
        $insights = array_merge($insights, $this->revenueGrowthTrendInsight($companyId));
        $insights = array_merge($insights, $this->revenueForecastInsight($companyId));

        // Appointments Insights
        $insights = array_merge($insights, $this->getAppointmentsInsights($companyId));
        $insights = array_merge($insights, $this->missedAppointmentsInsight($companyId));
        $insights = array_merge($insights, $this->highCancellationRateInsight($companyId));
        $insights = array_merge($insights, $this->topDoctorInsight($companyId));
        $insights = array_merge($insights, $this->getNoShowInsights($companyId));

        // Invoice Insights
        $insights = array_merge($insights, $this->getInvoiceInsights($companyId));
        $insights = array_merge($insights, $this->unpaidInvoicesInsight($companyId));

        // Patient Insights
        $insights = array_merge($insights, $this->getPatientInsights($companyId));

        return array_values(array_filter($insights));
    }

    // ==================== NEW INSIGHTS (with actions) ====================

    private function getRevenueInsights($companyId): array
    {
        $insights = [];
        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        $currentRevenue = Payment::query()
            ->where('company_id', $companyId)
            ->whereBetween('paid_at', [$currentMonth, Carbon::now()])
            ->sum('applied_amount');

        $lastMonthRevenue = Payment::query()
            ->where('company_id', $companyId)
            ->whereBetween('paid_at', [$lastMonth, $lastMonth->copy()->endOfMonth()])
            ->sum('applied_amount');

        if ($lastMonthRevenue > 0) {
            $growth = (($currentRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100;

            if ($growth > 20) {
                $insights[] = [
                    'category' => 'revenue',
                    'priority' => 'positive',
                    'message' => "Revenue growth of " . round($growth) . "% this month! 🚀",
                    'action' => [
                        'type' => 'navigate',
                        'url' => '/admin/erp/reports?type=revenue',
                        'label' => 'View revenue report'
                    ]
                ];
            } elseif ($growth < -10) {
                $insights[] = [
                    'category' => 'revenue',
                    'priority' => 'high',
                    'message' => "Revenue decreased by " . abs(round($growth)) . "% compared to last month",
                    'action' => [
                        'type' => 'navigate',
                        'url' => '/admin/erp/reports?type=revenue&compare=true',
                        'label' => 'Analyze revenue drop'
                    ]
                ];
            }
        }

        return $insights;
    }

    private function getAppointmentsInsights($companyId): array
    {
        $insights = [];
        $today = Carbon::today();
        $weekAgo = Carbon::today()->subDays(7);

        $totalAppointments = Appointment::query()
            ->where('company_id', $companyId)
            ->whereBetween('appointment_date', [$weekAgo, $today])
            ->count();

        $cancelledAppointments = Appointment::query()
            ->where('company_id', $companyId)
            ->whereBetween('appointment_date', [$weekAgo, $today])
            ->where('status', 'cancelled')
            ->count();

        if ($totalAppointments > 0) {
            $cancellationRate = ($cancelledAppointments / $totalAppointments) * 100;

            if ($cancellationRate > 20) {
                $insights[] = [
                    'category' => 'appointments',
                    'priority' => 'high',
                    'message' => "High cancellation rate: " . round($cancellationRate) . "% in the last 7 days",
                    'action' => [
                        'type' => 'navigate',
                        'url' => '/admin/erp/appointments?status=cancelled&period=week',
                        'label' => 'View cancelled appointments'
                    ]
                ];
            }
        }

        return $insights;
    }

    private function getInvoiceInsights($companyId): array
    {
        $insights = [];

        $unpaidInvoices = Invoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'unpaid')
            ->whereDate('issued_at', '<=', Carbon::now()->subDays(30))
            ->count();

        if ($unpaidInvoices > 5) {
            $insights[] = [
                'category' => 'invoices',
                'priority' => 'warning',
                'message' => "You have {$unpaidInvoices} unpaid invoices overdue by 30+ days",
                'action' => [
                    'type' => 'navigate',
                    'url' => '/admin/erp/invoices?status=unpaid&overdue=true',
                    'label' => 'View unpaid invoices'
                ]
            ];
        }

        return $insights;
    }

    private function getNoShowInsights($companyId): array
    {
        $insights = [];
        $monthStart = Carbon::now()->startOfMonth();

        $noShows = Appointment::query()
            ->where('company_id', $companyId)
            ->whereBetween('appointment_date', [$monthStart, Carbon::now()])
            ->where('status', 'no_show')
            ->count();

        if ($noShows > 3) {
            $insights[] = [
                'category' => 'appointments',
                'priority' => 'high',
                'message' => "High no-show rate: {$noShows} patients didn't show up this month",
                'action' => [
                    'type' => 'navigate',
                    'url' => '/admin/erp/appointments?status=no_show',
                    'label' => 'View no-show appointments'
                ]
            ];
        }

        return $insights;
    }

    private function getPatientInsights($companyId): array
    {
        $insights = [];
        $lastMonth = Carbon::now()->subMonth();

        $newPatients = Customer::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$lastMonth, Carbon::now()])
            ->count();

        if ($newPatients > 10) {
            $insights[] = [
                'category' => 'patients',
                'priority' => 'positive',
                'message' => "Great! {$newPatients} new patients joined this month",
                'action' => [
                    'type' => 'navigate',
                    'url' => '/admin/erp/patients?period=month',
                    'label' => 'View new patients'
                ]
            ];
        }

        return $insights;
    }

    // ==================== OLD INSIGHTS (with actions added) ====================

    /**
     * Detect revenue growing trend (3 consecutive days of growth)
     */
    public function revenueGrowthTrendInsight($companyId): ?array
    {
        $dates = [];
        $revenues = [];

        for ($i = 0; $i < 4; $i++) {
            $date = Carbon::today()->subDays($i);
            $dates[] = $date->toDateString();
            $revenues[] = (float) Payment::query()
                ->where('company_id', $companyId)
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
    public function revenueForecastInsight($companyId): ?array
    {
        $avgRevenue = (float) Payment::query()
            ->where('company_id', $companyId)
            ->whereDate('paid_at', '>=', Carbon::today()->subDays(7))
            ->whereDate('paid_at', '<', Carbon::today())
            ->sum('applied_amount') / 7;

        $todayRevenue = (float) Payment::query()
            ->where('company_id', $companyId)
            ->whereDate('paid_at', Carbon::today())
            ->sum('applied_amount');

        $expectedToday = max($avgRevenue, $todayRevenue);

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
    public function topDoctorInsight($companyId): ?array
    {
        $today = Carbon::today();

        $topDoctor = Appointment::query()
            ->where('company_id', $companyId)
            ->whereDate('appointment_date', $today)
            ->where('status', 'completed')
            ->select('doctor_id', 'doctor_name', DB::raw('COUNT(*) as completed_count'))
            ->groupBy('doctor_id', 'doctor_name')
            ->orderByDesc('completed_count')
            ->first();

        if ($topDoctor && $topDoctor->completed_count >= 3) {
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
    public function highCancellationRateInsight($companyId): ?array
    {
        $today = Carbon::today();

        $totalToday = Appointment::query()
            ->where('company_id', $companyId)
            ->whereDate('appointment_date', $today)
            ->count();

        $cancelledToday = Appointment::query()
            ->where('company_id', $companyId)
            ->whereDate('appointment_date', $today)
            ->where('status', 'cancelled')
            ->count();

        if ($totalToday >= 5) {
            $cancellationRate = ($cancelledToday / $totalToday) * 100;

            if ($cancellationRate > 30) {
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

    /**
     * Generate missed appointments insight
     */
    public function missedAppointmentsInsight($companyId): ?array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todayMissed = Appointment::query()
            ->where('company_id', $companyId)
            ->whereDate('appointment_date', $today)
            ->where('status', 'no_show')
            ->count();

        $yesterdayMissed = Appointment::query()
            ->where('company_id', $companyId)
            ->whereDate('appointment_date', $yesterday)
            ->where('status', 'no_show')
            ->count();

        if ($yesterdayMissed <= 0) {
            return null;
        }

        $change = (($todayMissed - $yesterdayMissed) / $yesterdayMissed) * 100;

        if ($change > 30) {
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
     * Generate unpaid invoices insight
     */
    public function unpaidInvoicesInsight($companyId): ?array
    {
        $unpaidCount = Invoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'unpaid')
            ->count();

        $overdueCount = Invoice::query()
            ->where('company_id', $companyId)
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
                'meta' => [
                    'unpaid_count' => $unpaidCount,
                    'overdue_count' => $overdueCount,
                ],
            ];
        }

        return null;
    }
}
