<?php

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Events\SubscriptionChanged;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class LogSubscriptionActivity
{
    public function __invoke($event)
    {
        $subscription = $event instanceof SubscriptionCreated ? $event->subscription : $event->newSubscription;
        $companyId = $subscription->company_id ?? null;
        $branchId = $subscription->branch_id ?? null; // ✅ جلب branch_id إن وجد

        ActivityLog::create([
            'company_id' => $companyId,
            'branch_id'  => $branchId, // ✅ إضافته للسجل
            'user_id' => auth()->id(),
            'action' => 'subscription.' . ($event instanceof SubscriptionCreated ? 'created' : 'changed'),
            'subject_type' => 'Subscription',
            'subject_id' => $subscription->id,
            'properties' => [
                'plan_id' => $subscription->plan_id,
                'branch_id' => $branchId, // ✅ تسجيله في الخصائص أيضاً
            ],
        ]);
    }
}
