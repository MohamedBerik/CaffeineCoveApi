<?php

namespace App\Http\Controllers\API\SaaS;

use App\Http\Controllers\Controller;
use App\Models\Company;
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

        // ✅ Run in Super Admin Context
        return Tenant::asSuperAdmin(function () use ($period) {

            // ==================== Stats ====================
            $totalCompanies = Company::count();
            $activeCompanies = Company::where('status', 'active')->count();
            $trialCompanies = Company::where('status', 'trial')->count();
            $suspendedCompanies = Company::where('status', 'suspended')->count();

            $activePercentage = $totalCompanies > 0
                ? ($activeCompanies / $totalCompanies) * 100
                : 0;

            // ==================== MRR (Monthly Recurring Revenue) ====================
            $mrr = DB::table('invoices')
                ->join('companies', 'companies.id', '=', 'invoices.company_id')
                ->where('companies.status', 'active')
                ->whereMonth('invoices.issued_at', now()->month)
                ->sum('invoices.total');

            $lastMonthMrr = DB::table('invoices')
                ->join('companies', 'companies.id', '=', 'invoices.company_id')
                ->where('companies.status', 'active')
                ->whereMonth('invoices.issued_at', now()->subMonth()->month)
                ->sum('invoices.total');

            $mrrGrowth = $lastMonthMrr > 0
                ? (($mrr - $lastMonthMrr) / $lastMonthMrr) * 100
                : 0;

            // ==================== Total Revenue ====================
            $dateRange = $this->getDateRange($period);

            $totalRevenue = DB::table('payments')
                ->whereBetween('paid_at', [$dateRange['start'], $dateRange['end']])
                ->sum('applied_amount');

            $previousRevenue = DB::table('payments')
                ->whereBetween('paid_at', [$dateRange['previous_start'], $dateRange['previous_end']])
                ->sum('applied_amount');

            $revenueGrowth = $previousRevenue > 0
                ? (($totalRevenue - $previousRevenue) / $previousRevenue) * 100
                : 0;

            // ==================== Companies Growth ====================
            $newCompanies = Company::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count();
            $churnedCompanies = Company::where('status', 'cancelled')
                ->whereBetween('updated_at', [$dateRange['start'], $dateRange['end']])
                ->count();

            $companiesGrowth = $newCompanies - $churnedCompanies;
            $companiesGrowthPercent = $totalCompanies > 0
                ? ($companiesGrowth / $totalCompanies) * 100
                : 0;

            // ==================== Trial Growth ====================
            $trialGrowth = Company::where('status', 'trial')
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count();

            // ==================== MRR Chart Data ====================
            $mrrData = $this->getMrrChartData();

            // ==================== Growth Chart Data ====================
            $growthData = $this->getGrowthChartData();

            // ==================== Companies by Status ====================
            $companiesByStatus = [
                'active' => $activeCompanies,
                'trial' => $trialCompanies,
                'suspended' => $suspendedCompanies,
                'cancelled' => Company::where('status', 'cancelled')->count(),
            ];

            // ==================== Top Clinics ====================
            $topClinics = Company::where('status', 'active')
                ->withSum(['invoices as total_revenue' => function ($q) {
                    $q->whereMonth('issued_at', now()->month);
                }], 'total')
                ->withCount(['appointments as total_appointments' => function ($q) {
                    $q->whereMonth('appointment_date', now()->month);
                }])
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
                        'appointments' => $company->total_appointments ?? 0,
                        'growth' => round($growth, 1),
                    ];
                });

            // ==================== Recent Companies ====================
            $recentCompanies = Company::latest()
                ->limit(10)
                ->get(['id', 'name', 'slug', 'status', 'created_at', 'trial_ends_at']);

            // ==================== Recent Activities ====================
            $recentActivities = ActivityLog::whereIn('action', [
                'company.created',
                'company.activated',
                'company.suspended',
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
                });

            return response()->json([
                'msg' => 'SaaS Dashboard',
                'status' => 200,
                'data' => [
                    'stats' => [
                        'total_companies' => $totalCompanies,
                        'active_companies' => $activeCompanies,
                        'trial_companies' => $trialCompanies,
                        'suspended_companies' => $suspendedCompanies,
                        'active_percentage' => round($activePercentage, 1),
                        'mrr' => $mrr,
                        'mrr_growth' => round($mrrGrowth, 1),
                        'total_revenue' => $totalRevenue,
                        'revenue_growth' => round($revenueGrowth, 1),
                        'companies_growth' => $companiesGrowthPercent,
                        'trial_growth' => $trialGrowth,
                    ],
                    'mrr' => $mrrData,
                    'growth' => $growthData,
                    'companies_by_status' => $companiesByStatus,
                    'top_clinics' => $topClinics,
                    'recent_companies' => $recentCompanies,
                    'recent_activities' => $recentActivities,
                ],
            ]);
        });
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
            $mrr = DB::table('invoices')
                ->join('companies', 'companies.id', '=', 'invoices.company_id')
                ->where('companies.status', 'active')
                ->whereYear('invoices.issued_at', $date->year)
                ->whereMonth('invoices.issued_at', $date->month)
                ->sum('invoices.total');

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
}
