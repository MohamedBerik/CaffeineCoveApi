<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckGracePeriods extends Command
{
    protected $signature = 'billing:check-grace-periods';
    protected $description = 'تعليق الاشتراكات اللي انتهت فترة السماح بتاعتها';

    public function handle()
    {
        $this->info('Checking grace periods...');

        // ✅ البحث عن اشتراكات انتهت فترة السماح
        $expired = Subscription::where('status', 'active')
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<', now())
            ->cursor();

        $count = 0;
        foreach ($expired as $subscription) {
            $subscription->update([
                'status' => 'suspended',
                'suspended_at' => now(),
                'suspension_reason' => 'grace_period_expired',
            ]);

            // ✅ تعليق الشركة المرتبطة أيضًا
            $company = \App\Models\Company::find($subscription->company_id);
            if ($company && $company->status === 'active') {
                $company->update(['status' => 'suspended']);
            }

            Log::warning('Subscription suspended: grace period expired', [
                'subscription_id' => $subscription->id,
                'company_id' => $subscription->company_id,
                'company_status' => $company?->status,
                'grace_ended_at' => $subscription->grace_period_ends_at,
            ]);

            $count++;
        }

        $this->info("Suspended {$count} subscriptions.");
        Log::info('Grace period check completed', ['suspended' => $count]);

        return 0;
    }
}
