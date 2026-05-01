<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\TestJob;

class TestQueueJob extends Command
{
    protected $signature = 'test:queue';
    protected $description = 'Dispatch a test job';

    public function handle()
    {
        // ✅ أرسل المهمة مع معرف مستأجر وهمي (1)
        TestJob::dispatch(1);

        $this->info('Test job dispatched');
    }
}
