<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;

class SystemStats extends Command
{
    protected $signature = 'system:stats';
    protected $description = 'Display system statistics';

    public function handle()
    {
        $this->info('📊 System Statistics');
        $this->info('==================');

        // Companies
        $this->info('🏢 Companies:');
        $this->info('   Total: ' . Company::count());
        $this->info('   Active: ' . Company::where('status', 'active')->count());
        $this->info('   Trial: ' . Company::where('status', 'trial')->count());
        $this->info('   Suspended: ' . Company::where('status', 'suspended')->count());

        // Users
        $this->info('👥 Users:');
        $this->info('   Total: ' . User::count());
        $this->info('   Super Admins: ' . User::where('is_super_admin', true)->count());
        $this->info('   Company Admins: ' . User::where('role', 'admin')->count());

        // Subscriptions
        $this->info('💳 Subscriptions:');
        $this->info('   Active: ' . Subscription::where('status', 'active')->count());
        $this->info('   MRR: ' . number_format(Subscription::where('status', 'active')->sum('amount'), 2) . ' EGP');

        return 0;
    }
}
