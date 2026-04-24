<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class BillingReminder extends Command
{
    protected $signature = 'billing:reminders';
    protected $description = 'Check subscriptions and send reminders';

    public function handle()
    {
        // ✅ اشتراكات هتنتهي خلال 7 أيام
        $endingSoon = Subscription::where('status', 'active')
            ->whereDate('ends_at', '<=', now()->addDays(7))
            ->whereDate('ends_at', '>', now())
            ->with('company')
            ->get();

        foreach ($endingSoon as $subscription) {
            // إرسال إيميل تذكير
            $this->info("Reminder: {$subscription->company->name} ends on {$subscription->ends_at}");
        }

        // ✅ اشتراكات انتهت
        $expired = Subscription::where('status', 'active')
            ->whereDate('ends_at', '<', now())
            ->with('company')
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);
            $this->info("Expired: {$subscription->company->name}");
        }

        // ✅ Trial ending
        $trialEnding = \App\Models\Company::where('status', 'trial')
            ->whereDate('trial_ends_at', '<=', now()->addDays(3))
            ->whereDate('trial_ends_at', '>', now())
            ->get();

        foreach ($trialEnding as $company) {
            $this->info("Trial ending: {$company->name} on {$company->trial_ends_at}");
        }

        return 0;
    }
}
