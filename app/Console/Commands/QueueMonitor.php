<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueMonitor extends Command
{
    protected $signature = 'queue:monitor';
    protected $description = 'مراقبة حالة الطابور وإظهار إحصائيات';

    public function handle()
    {
        $this->info('=== Queue Monitor ===');
        $this->newLine();

        // عدد الـ Jobs المنتظرة
        $pending = DB::table('jobs')->count();
        $this->info("Pending Jobs: {$pending}");

        // عدد الـ Jobs الفاشلة
        $failed = DB::table('failed_jobs')->count();
        $this->warn("Failed Jobs: {$failed}");

        // توزيع الـ Queue
        $queues = DB::table('jobs')
            ->select('queue', DB::raw('count(*) as count'))
            ->groupBy('queue')
            ->get();

        $this->info("\nQueue Distribution:");
        foreach ($queues as $queue) {
            $this->line("  - {$queue->queue}: {$queue->count} jobs");
        }

        // آخر 5 Jobs فشلت
        if ($failed > 0) {
            $this->warn("\nLast 5 Failed Jobs:");
            $lastFailed = DB::table('failed_jobs')
                ->select('uuid', 'queue', 'failed_at')
                ->orderBy('failed_at', 'desc')
                ->limit(5)
                ->get();

            foreach ($lastFailed as $job) {
                $this->line("  - {$job->queue} ({$job->failed_at})");
            }
        }

        // صحة الطابور
        $this->newLine();
        if ($pending > 100) {
            $this->error("⚠️ Queue is backed up! {$pending} jobs pending.");
        } elseif ($failed > 10) {
            $this->error("⚠️ Too many failed jobs: {$failed}");
        } else {
            $this->info("✅ Queue is healthy");
        }

        return 0;
    }
}
