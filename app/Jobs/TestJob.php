<?php

namespace App\Jobs;

use App\Services\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tenantId;

    public function __construct($tenantId)
    {
        $this->tenantId = $tenantId;
    }

    // في TestQueueJob.php
    public function handle()
    {
        // ✅ استخدم dispatch مع tenantId وهمي
        dispatch(new \App\Jobs\TestJob(1)); // 1 = tenantId وهمي

        $this->info('Test job dispatched');
    }
}
