<?php

namespace App\Http\Controllers\API\SaaS;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\BillingInvoice;
use App\Models\ActivityLog;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SaasDashboardController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'month');

        return Tenant::asSuperAdmin(function () use ($period) {

            // ==================== BILLING KPIs ====================
            $activeSubscriptions = Subscription::where('status', 'active')->count();
            $mrr = (float) Subscription::where('status', 'active')->sum('amount');
            $totalRevenue = (float) BillingInvoice::where('status', 'paid')->sum('total');
            $churnRate = $this->calculateChurnRate();
            $avgRevenue = $activeSubscriptions > 0 ? $mrr / $activeSubscriptions : 0;

            $dateRange = $this->getDateRange($period);

            // ==================== STATS ====================
            $stats = [
                'total_companies' => Company::count(),
                'active_companies' => Company::where('status', 'active')->count(),
                'trial_companies' => Company::where('status', 'trial')->count(),
                'suspended_companies' => Company::where('status', 'suspended')->count(),
                'active_percentage' => Company::count() > 0 ? round((Company::where('status', 'active')->count() / Company::count()) * 100, 1) : 0,
                'mrr' => $mrr,
                'mrr_growth' => $this->calculateMrrGrowth(),
                'total_revenue' => $totalRevenue,
                'revenue_growth' => $this->calculateRevenueGrowth($dateRange),
                'companies_growth' => $this->calculateCompaniesGrowth($dateRange),
                'trial_growth' => $this->calculateTrialGrowth($dateRange),
                'active_subscriptions' => $activeSubscriptions,
                'churn_rate' => round($churnRate, 1),
                'avg_revenue_per_company' => round($avgRevenue, 2),
            ];

            // ==================== MRR CHART ====================
            $mrrData = $this->getMrrChartData();

            // ==================== GROWTH CHART ====================
            $growthData = $this->getGrowthChartData();

            // ==================== COMPANIES BY STATUS ====================
            $companiesByStatus = [
                'active' => Company::where('status', 'active')->count(),
                'trial' => Company::where('status', 'trial')->count(),
                'suspended' => Company::where('status', 'suspended')->count(),
                'cancelled' => Company::where('status', 'cancelled')->count(),
            ];

            // ==================== TOP CLINICS ====================
            $topClinics = $this->getTopClinics();

            // ==================== RECENT COMPANIES ====================
            $recentCompanies = Company::latest()->limit(10)->get(['id', 'name', 'slug', 'status', 'created_at', 'trial_ends_at']);

            // ==================== RECENT TRANSACTIONS ====================
            $recentTransactions = $this->getRecentTransactions();

            // ==================== RECENT ACTIVITIES ====================
            $recentActivities = ActivityLog::whereIn('action', [
                'subscription.created',
                'subscription.cancelled',
                'subscription.changed',
                'payment.received',
                'company.created',
            ])
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    return [
                        'type' => str_replace('.', '_', $log->action),
                        'message' => $log->action,
                        'created_at' => $log->created_at,
                    ];
                });

            return response()->json([
                'msg' => 'SaaS Dashboard',
                'status' => 200,
                'data' => [
                    'stats' => $stats,
                    'mrr' => $mrrData,
                    'growth' => $growthData,
                    'companies_by_status' => $companiesByStatus,
                    'top_clinics' => $topClinics,
                    'recent_companies' => $recentCompanies,
                    'recent_transactions' => $recentTransactions,
                    'recent_activities' => $recentActivities,
                ],
            ]);
        });
    }

    // ==================== HELPER METHODS ====================

    private function calculateChurnRate(): float
    {
        $totalActive = Subscription::where('status', 'active')->count();
        $cancelledThisMonth = Subscription::where('status', 'cancelled')
            ->whereMonth('updated_at', now()->month)
            ->count();

        return $totalActive > 0 ? ($cancelledThisMonth / $totalActive) * 100 : 0;
    }

    private function calculateMrrGrowth(): float
    {
        $currentMrr = (float) Subscription::where('status', 'active')->sum('amount');
        $lastMonthMrr = (float) Subscription::where('status', 'active')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('amount');

        return $lastMonthMrr > 0 ? (($currentMrr - $lastMonthMrr) / $lastMonthMrr) * 100 : 0;
    }

    private function calculateRevenueGrowth(array $dateRange): float
    {
        $current = (float) BillingInvoice::where('status', 'paid')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->sum('total');

        $previous = (float) BillingInvoice::where('status', 'paid')
            ->whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
            ->sum('total');

        return $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;
    }

    private function calculateCompaniesGrowth(array $dateRange): float
    {
        $current = Company::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count();
        $previous = Company::whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])->count();

        return $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;
    }

    private function calculateTrialGrowth(array $dateRange): float
    {
        $current = Company::where('status', 'trial')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();

        return $current;
    }

    private function getDateRange(string $period): array
    {
        return match ($period) {
            'day' => [
                'start' => now()->startOfDay(),
                'end' => now()->endOfDay(),
                'previous_start' => now()->subDay()->startOfDay(),
                'previous_end' => now()->subDay()->endOfDay(),
            ],
            'week' => [
                'start' => now()->startOfWeek(),
                'end' => now()->endOfWeek(),
                'previous_start' => now()->subWeek()->startOfWeek(),
                'previous_end' => now()->subWeek()->endOfWeek(),
            ],
            'month' => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
                'previous_start' => now()->subMonth()->startOfMonth(),
                'previous_end' => now()->subMonth()->endOfMonth(),
            ],
            'year' => [
                'start' => now()->startOfYear(),
                'end' => now()->endOfYear(),
                'previous_start' => now()->subYear()->startOfYear(),
                'previous_end' => now()->subYear()->endOfYear(),
            ],
            default => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
                'previous_start' => now()->subMonth()->startOfMonth(),
                'previous_end' => now()->subMonth()->endOfMonth(),
            ],
        };
    }

    private function getMrrChartData(): array
    {
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $mrr = (float) Subscription::where('status', 'active')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('amount');

            $data[] = [
                'month' => $date->format('M Y'),
                'value' => $mrr,
            ];
        }
        return $data;
    }

    private function getGrowthChartData(): array
    {
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            $new = Company::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $churned = Company::where('status', 'cancelled')
                ->whereYear('updated_at', $date->year)
                ->whereMonth('updated_at', $date->month)
                ->count();

            $data[] = [
                'month' => $date->format('M Y'),
                'new' => $new,
                'churned' => $churned,
            ];
        }
        return $data;
    }

    private function getTopClinics(): array
    {
        return Company::where('status', 'active')
            ->withSum(['subscriptions as total_revenue' => function ($q) {
                $q->where('status', 'active');
            }], 'amount')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get()
            ->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'revenue' => $company->total_revenue ?? 0,
                    'appointments' => 0,
                    'growth' => 0,
                ];
            })
            ->toArray();
    }

    private function getRecentTransactions(): array
    {
        return BillingInvoice::where('status', 'paid')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'amount' => $invoice->total,
                    'company_id' => $invoice->company_id,
                    'created_at' => $invoice->created_at,
                ];
            })
            ->toArray();
    }
}
