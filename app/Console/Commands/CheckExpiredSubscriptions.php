<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckExpiredSubscriptions extends Command
{
    protected $signature = 'billing:check-expired';
    protected $description = 'تعليق الاشتراكات المنتهية (بدون Grace Period)';

    public function handle()
    {
        $this->info('Checking expired subscriptions...');

        // ✅ اشتراكات انتهت وليس لها Grace Period
        $expired = Subscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->whereNull('grace_period_ends_at')
            ->cursor();

        $count = 0;
        foreach ($expired as $subscription) {
            $subscription->update([
                'status' => 'expired',
            ]);

            // ✅ تعليق الشركة المرتبطة
            $company = \App\Models\Company::find($subscription->company_id);
            if ($company && $company->status === 'active') {
                $company->update(['status' => 'suspended']);
            }

            Log::info('Subscription expired', [
                'subscription_id' => $subscription->id,
                'company_id' => $subscription->company_id,
                'company_status' => $company?->status,
            ]);

            $count++;
        }

        $this->info("Expired {$count} subscriptions.");
        return 0;
    }
}
