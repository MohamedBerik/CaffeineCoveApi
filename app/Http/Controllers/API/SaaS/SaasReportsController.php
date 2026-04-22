<?php

namespace App\Http\Controllers\API\SaaS;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ActivityLog;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SaasReportsController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'month');

        return Tenant::asSuperAdmin(function () use ($period) {

            $dateRange = $this->getDateRange($period);

            // Overview Stats
            $overview = [
                'active' => Company::where('status', 'active')->count(),
                'trial' => Company::where('status', 'trial')->count(),
                'suspended' => Company::where('status', 'suspended')->count(),
                'cancelled' => Company::where('status', 'cancelled')->count(),
            ];

            // KPIs
            $totalCompanies = Company::count();
            $activeCompanies = $overview['active'];
            $mrr = $this->getMRR();
            $totalRevenue = $this->getTotalRevenue($dateRange);
            $churnRate = $this->getChurnRate($dateRange);

            $kpis = [
                'total_companies' => $totalCompanies,
                'active_companies' => $activeCompanies,
                'companies_growth' => $this->getCompaniesGrowth($dateRange),
                'active_growth' => $this->getActiveGrowth($dateRange),
                'mrr' => $mrr,
                'mrr_growth' => $this->getMrrGrowth(),
                'total_revenue' => $totalRevenue,
                'revenue_growth' => $this->getRevenueGrowth($dateRange),
                'avg_revenue' => $activeCompanies > 0 ? $totalRevenue / $activeCompanies : 0,
                'avg_revenue_growth' => 0,
                'churn_rate' => $churnRate,
                'net_growth' => $this->getNetGrowth($dateRange),
                'new_companies' => $this->getNewCompanies($dateRange),
                'churned_companies' => $this->getChurnedCompanies($dateRange),
                'retention_rate' => 100 - $churnRate,
                'avg_lifetime' => 12,
            ];

            // Chart Data
            $revenueData = $this->getRevenueChartData();
            $companiesGrowth = $this->getCompaniesGrowthChartData();
            $churnData = $this->getChurnChartData();

            // Plans Distribution
            $plansDistribution = $this->getPlansDistribution();

            // Top Companies
            $topCompanies = $this->getTopCompanies();

            // Recent Activity
            $recentActivity = $this->getRecentActivity();

            return response()->json([
                'msg' => 'SaaS Reports',
                'status' => 200,
                'data' => [
                    'overview' => $overview,
                    'kpis' => $kpis,
                    'revenue' => $revenueData,
                    'companies_growth' => $companiesGrowth,
                    'churn' => $churnData,
                    'plans_distribution' => $plansDistribution,
                    'top_companies' => $topCompanies,
                    'recent_activity' => $recentActivity,
                ],
            ]);
        });
    }

    private function getDateRange(string $period): array
    {
        return match ($period) {
            'day' => ['start' => now()->startOfDay(), 'end' => now()->endOfDay()],
            'week' => ['start' => now()->startOfWeek(), 'end' => now()->endOfWeek()],
            'month' => ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()],
            'year' => ['start' => now()->startOfYear(), 'end' => now()->endOfYear()],
            default => ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()],
        };
    }

    private function getMRR(): float
    {
        return (float) DB::table('invoices')
            ->join('companies', 'companies.id', '=', 'invoices.company_id')
            ->where('companies.status', 'active')
            ->whereMonth('invoices.issued_at', now()->month)
            ->sum('invoices.total');
    }

    private function getTotalRevenue(array $dateRange): float
    {
        return (float) DB::table('payments')
            ->whereBetween('paid_at', [$dateRange['start'], $dateRange['end']])
            ->sum('applied_amount');
    }

    private function getCompaniesGrowth(array $dateRange): float
    {
        $current = Company::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count();
        $previous = Company::whereBetween('created_at', [
            (clone $dateRange['start'])->subMonth(),
            (clone $dateRange['end'])->subMonth()
        ])->count();

        return $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;
    }

    private function getActiveGrowth(array $dateRange): float
    {
        return $this->getCompaniesGrowth($dateRange);
    }

    private function getMrrGrowth(): float
    {
        $current = $this->getMRR();
        $previous = (float) DB::table('invoices')
            ->join('companies', 'companies.id', '=', 'invoices.company_id')
            ->where('companies.status', 'active')
            ->whereMonth('invoices.issued_at', now()->subMonth()->month)
            ->sum('invoices.total');

        return $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;
    }

    private function getRevenueGrowth(array $dateRange): float
    {
        $current = $this->getTotalRevenue($dateRange);
        $previous = (float) DB::table('payments')
            ->whereBetween('paid_at', [
                (clone $dateRange['start'])->subMonth(),
                (clone $dateRange['end'])->subMonth()
            ])
            ->sum('applied_amount');

        return $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;
    }

    private function getChurnRate(array $dateRange): float
    {
        $churned = Company::where('status', 'cancelled')
            ->whereBetween('updated_at', [$dateRange['start'], $dateRange['end']])
            ->count();

        $total = Company::count();

        return $total > 0 ? ($churned / $total) * 100 : 0;
    }

    private function getNetGrowth(array $dateRange): int
    {
        return $this->getNewCompanies($dateRange) - $this->getChurnedCompanies($dateRange);
    }

    private function getNewCompanies(array $dateRange): int
    {
        return Company::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count();
    }

    private function getChurnedCompanies(array $dateRange): int
    {
        return Company::where('status', 'cancelled')
            ->whereBetween('updated_at', [$dateRange['start'], $dateRange['end']])
            ->count();
    }

    private function getRevenueChartData(): array
    {
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $revenue = (float) DB::table('payments')
                ->whereYear('paid_at', $date->year)
                ->whereMonth('paid_at', $date->month)
                ->sum('applied_amount');

            $mrr = (float) DB::table('invoices')
                ->join('companies', 'companies.id', '=', 'invoices.company_id')
                ->where('companies.status', 'active')
                ->whereYear('invoices.issued_at', $date->year)
                ->whereMonth('invoices.issued_at', $date->month)
                ->sum('invoices.total');

            $subscriptions = DB::table('subscriptions')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $data[] = [
                'month' => $date->format('M Y'),
                'revenue' => $revenue,
                'mrr' => $mrr,
                'subscriptions' => $subscriptions,
            ];
        }
        return $data;
    }

    private function getCompaniesGrowthChartData(): array
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
                'net' => $new - $churned,
            ];
        }
        return $data;
    }

    private function getChurnChartData(): array
    {
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            $churned = Company::where('status', 'cancelled')
                ->whereYear('updated_at', $date->year)
                ->whereMonth('updated_at', $date->month)
                ->count();

            $total = Company::count();
            $churnRate = $total > 0 ? ($churned / $total) * 100 : 0;

            $data[] = [
                'month' => $date->format('M Y'),
                'churn_rate' => round($churnRate, 1),
                'churned_count' => $churned,
            ];
        }
        return $data;
    }

    private function getPlansDistribution(): array
    {
        // مؤقت - لو مفيش جدول plans
        return [
            'Basic' => 45,
            'Professional' => 30,
            'Enterprise' => 15,
            'Trial' => 10,
        ];
    }

    private function getTopCompanies(): array
    {
        return Company::where('status', 'active')
            ->withSum(['invoices as total_revenue' => function ($q) {
                $q->whereMonth('issued_at', now()->month);
            }], 'total')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get()
            ->map(function ($company) {
                $lastMonthRevenue = $company->invoices()
                    ->whereMonth('issued_at', now()->subMonth()->month)
                    ->sum('total');

                $growth = $lastMonthRevenue > 0
                    ? (($company->total_revenue - $lastMonthRevenue) / $lastMonthRevenue) * 100
                    : 0;

                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'revenue' => $company->total_revenue ?? 0,
                    'growth' => round($growth, 1),
                ];
            })
            ->toArray();
    }

    private function getRecentActivity(): array
    {
        return ActivityLog::whereIn('action', [
            'company.created',
            'company.activated',
            'subscription.created',
            'payment.received',
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
            })
            ->toArray();
    }
}
