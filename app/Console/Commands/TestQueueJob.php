<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestQueueJob extends Command
{
    protected $signature = 'test:queue';
    protected $description = 'Dispatch a test job';

    public function handle()
    {
        dispatch_sync(function () {
            Log::info('Test job executed successfully!');
        });

        $this->info('Test job dispatched');
    }
}
