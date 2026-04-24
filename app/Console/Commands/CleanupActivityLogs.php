<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

class CleanupActivityLogs extends Command
{
    protected $signature = 'cleanup:activity-logs {--days=90}';
    protected $description = 'Clean old activity logs';

    public function handle()
    {
        $days = (int) $this->option('days');

        $count = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$count} old activity logs (older than {$days} days).");

        return 0;
    }
}
