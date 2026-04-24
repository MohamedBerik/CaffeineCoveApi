<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class CheckFailedPayments extends Command
{
    protected $signature = 'billing:check-failed';
    protected $description = 'Check failed payments and mark subscriptions as past_due';

    public function handle()
    {
        // ✅ اشتراكات pending لمدة أكتر من 24 ساعة
        $stalePending = Subscription::where('status', 'pending')
            ->where('created_at', '<', now()->subHours(24))
            ->get();

        foreach ($stalePending as $subscription) {
            $subscription->update(['status' => 'failed']);
            $this->info("Marked failed: Subscription #{$subscription->id}");
        }

        // ✅ اشتراكات past_due لمدة أكتر من 14 يوم
        $gracePeriod = Subscription::where('status', 'past_due')
            ->where('updated_at', '<', now()->subDays(14))
            ->get();

        foreach ($gracePeriod as $subscription) {
            $subscription->update(['status' => 'cancelled']);
            $this->info("Cancelled: Subscription #{$subscription->id}");
        }

        return 0;
    }
}
