<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;

class ExpireTrials extends Command
{
    protected $signature = 'trials:expire';
    protected $description = 'Suspend companies whose trial period has ended';

    public function handle()
    {
        $expired = Company::where('status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->update(['status' => 'suspended']);

        $this->info("Suspended {$expired} expired trial companies.");
    }
}
