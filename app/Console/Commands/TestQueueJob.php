<?php

namespace App\Console\Commands;

use App\Jobs\TestJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestQueueJob extends Command
{
    protected $signature = 'test:queue';
    protected $description = 'Dispatch a test job';

    public function handle()
    {
        TestJob::dispatch();

        $this->info('Test job dispatched');
    }
}
