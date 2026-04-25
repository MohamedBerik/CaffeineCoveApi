<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\BillingInvoice;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SystemStats extends Command
{
    protected $signature = 'system:stats';
    protected $description = 'عرض إحصائيات النظام الكاملة';

    public function handle()
    {
        $this->newLine();
        $this->info('╔════════════════════════════════════════════╗');
        $this->info('║         SYSTEM STATISTICS REPORT           ║');
        $this->info('╚════════════════════════════════════════════╝');
        $this->newLine();

        // ========== الشركات ==========
        $this->line('═══ 🏢 Companies ═══');
        $this->table(
            ['Status', 'Count'],
            [
                ['Active', Company::where('status', 'active')->count()],
                ['Trial', Company::where('status', 'trial')->count()],
                ['Suspended', Company::where('status', 'suspended')->count()],
                ['Total', Company::count()],
            ]
        );

        // ========== المستخدمين ==========
        $this->line('═══ 👥 Users ═══');
        $this->table(
            ['Role', 'Count'],
            [
                ['Super Admins', User::where('is_super_admin', true)->count()],
                ['Company Admins', User::where('role', 'admin')->count()],
                ['Total Users', User::count()],
            ]
        );

        // ========== الاشتراكات ==========
        $this->line('═══ 💳 Subscriptions ═══');
        $mrr = Subscription::where('status', 'active')->sum('amount');
        $this->table(
            ['Status', 'Count', 'MRR'],
            [
                ['Active', Subscription::where('status', 'active')->count(), number_format($mrr, 2) . ' EGP'],
                ['Past Due', Subscription::where('status', 'past_due')->count(), '-'],
                ['Cancelled (Month)', Subscription::where('status', 'cancelled')->whereMonth('updated_at', now()->month)->count(), '-'],
                ['Total', Subscription::count(), '-'],
            ]
        );

        // ========== الإيرادات ==========
        $this->line('═══ 💰 Revenue (This Month) ═══');
        $monthRevenue = BillingInvoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->sum('total');
        $invoiceCount = BillingInvoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->count();
        $this->info("  Total Revenue: " . number_format($monthRevenue, 2) . ' EGP');
        $this->info("  Paid Invoices: {$invoiceCount}");
        $this->newLine();

        // ========== حالة النظام ==========
        $this->line('═══ 🔧 System Health ═══');

        // Queue
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $queueStatus = $failedJobs > 5 ? '⚠️ Warning' : ($pendingJobs > 50 ? '⚠️ Busy' : '✅ Healthy');
        $this->info("  Queue: {$queueStatus} (Pending: {$pendingJobs}, Failed: {$failedJobs})");

        // Webhooks (Last 24h)
        $webhookStats = DB::table('webhook_logs')
            ->select('status', DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('status')
            ->pluck('count', 'status');

        $this->info("  Webhooks (24h):");
        foreach ($webhookStats as $status => $count) {
            $emoji = match ($status) {
                'success' => '✅',
                'failed' => '❌',
                'pending' => '⏳',
                'duplicate_ignored' => '🔄',
                default => '📝'
            };
            $this->info("    {$emoji} {$status}: {$count}");
        }

        // Database Size
        $dbName = config('database.connections.mysql.database');
        $dbSize = DB::select("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size FROM information_schema.tables WHERE table_schema = ?", [$dbName]);
        $this->info("  Database Size: " . ($dbSize[0]->size ?? 0) . ' MB');

        // PHP Version
        $this->info("  PHP Version: " . PHP_VERSION);
        $this->info("  Laravel Version: " . app()->version());
        $this->info("  Environment: " . app()->environment());

        $this->newLine();
        $this->info('════════════════════════════════════════════');
        $this->info('  Report generated at: ' . now()->toDateTimeString());
        $this->info('════════════════════════════════════════════');

        return 0;
    }
}
