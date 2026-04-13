<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InsightService
{
    /**
     * Generate revenue insight based on day-over-day change.
     */
    public function revenueInsight($companyId): ?array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todayRevenue = (float) Payment::query()
            ->where('company_id', $companyId)
            ->whereDate('paid_at', $today)
            ->sum('applied_amount');

        $yesterdayRevenue = (float) Payment::query()
            ->where('company_id', $companyId)
            ->whereDate('paid_at', $yesterday)
            ->sum('applied_amount');

        if ($yesterdayRevenue <= 0) {
            return null;
        }

        $change = (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100;

        // Drop > 20% → insight
        if ($change < -20) {
            return [
                'type' => 'insight',
                'category' => 'revenue',
                'priority' => 'high',
                'message' => "Revenue dropped by " . round(abs($change)) . "%",
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

        // Increase > 30% → insight
        if ($change > 30) {
            return [
                'type' => 'insight',
                'category' => 'appointments',
                'priority' => 'medium',
                'message' => "Missed appointments increased by " . round($change) . "%",
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
    public function unpaidInvoicesInsight($companyId): ?array
    {
        $unpaidCount = \App\Models\Invoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'unpaid')
            ->count();

        $overdueCount = \App\Models\Invoice::query()
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
                'meta' => [
                    'unpaid_count' => $unpaidCount,
                    'overdue_count' => $overdueCount,
                ],
            ];
        }

        return null;
    }

    /**
     * Get all insights for a company.
     */
    public function getAllInsights($companyId): array
    {
        $insights = array_filter([
            $this->revenueInsight($companyId),
            $this->missedAppointmentsInsight($companyId),
            $this->unpaidInvoicesInsight($companyId),
            $this->revenueGrowthTrendInsight($companyId), // ✅ أضف هنا
            $this->revenueForecastInsight($companyId), // ✅ أضف هنا
            $this->topDoctorInsight($companyId), // ✅ أضف هنا
            $this->highCancellationRateInsight($companyId), // ✅ أضف هنا




        ]);

        return array_values($insights);
    }

    /**
     * Detect revenue growing trend (3 consecutive days of growth)
     */
    public function revenueGrowthTrendInsight($companyId): ?array
    {
        $dates = [];
        $revenues = [];

        // جمع إيرادات آخر 4 أيام
        for ($i = 0; $i < 4; $i++) {
            $date = Carbon::today()->subDays($i);
            $dates[] = $date->toDateString();
            $revenues[] = (float) Payment::query()
                ->where('company_id', $companyId)
                ->whereDate('paid_at', $date)
                ->sum('applied_amount');
        }

        // عكس الترتيب عشان يبقى من الأقدم للأحدث
        $revenues = array_reverse($revenues);

        // هل آخر 3 أيام (اليوم، أمس، قبل أمس) في تزايد؟
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
        // متوسط إيرادات آخر 7 أيام (ما عدا اليوم)
        $avgRevenue = (float) Payment::query()
            ->where('company_id', $companyId)
            ->whereDate('paid_at', '>=', Carbon::today()->subDays(7))
            ->whereDate('paid_at', '<', Carbon::today())
            ->sum('applied_amount') / 7;

        // إيرادات اليوم حتى الآن
        $todayRevenue = (float) Payment::query()
            ->where('company_id', $companyId)
            ->whereDate('paid_at', Carbon::today())
            ->sum('applied_amount');

        $expectedToday = max($avgRevenue, $todayRevenue);

        if ($expectedToday > 1000) { // لو المتوقع > 1000 جنيه
            return [
                'type' => 'insight',
                'category' => 'revenue',
                'priority' => 'low',
                'message' => "Expected revenue today: " . number_format($expectedToday) . " EGP",
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
}
