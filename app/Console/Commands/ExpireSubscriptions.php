<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'billing:expire-companies';
    protected $description = 'Suspend companies whose paid subscription has expired beyond grace period';

    public function handle()
    {
        // جلب الشركات النشطة التي لديها اشتراك منتهي (مع أو بدون فترة سماح)
        $expiredSubscriptions = Subscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->where(function ($q) {
                // إما بدون فترة سماح، أو فترة السماح انتهت
                $q->whereNull('grace_period_ends_at')
                    ->orWhere('grace_period_ends_at', '<', now());
            })
            ->get();

        $count = 0;
        foreach ($expiredSubscriptions as $subscription) {
            $company = Company::find($subscription->company_id);
            if ($company && $company->status === 'active') {
                $company->update(['status' => 'suspended']);
                $subscription->update(['status' => 'expired']);
                $count++;
            }
        }

        $this->info("Suspended {$count} companies due to expired subscriptions.");
    }
}
