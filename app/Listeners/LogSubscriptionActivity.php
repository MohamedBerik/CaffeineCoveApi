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

        ActivityLog::create([
            'company_id' => $companyId,
            'user_id' => auth()->id(),
            'action' => 'subscription.' . ($event instanceof SubscriptionCreated ? 'created' : 'changed'),
            'subject_type' => 'Subscription',
            'subject_id' => $subscription->id,
            'properties' => ['plan_id' => $subscription->plan_id],
        ]);
    }
}
