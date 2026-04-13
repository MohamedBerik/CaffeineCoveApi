<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Support\Carbon;

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
        ]);

        return array_values($insights);
    }
}
