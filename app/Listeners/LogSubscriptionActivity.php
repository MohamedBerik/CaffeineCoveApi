<?php

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Events\SubscriptionChanged;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class LogSubscriptionActivity
{
    public function handle($event)
    {
        ActivityLog::create([
            'company_id' => $event->subscription->company_id ?? ($event->oldSubscription->company_id ?? null),
            'user_id' => auth()->id(),
            'action' => 'subscription.' . ($event instanceof SubscriptionCreated ? 'created' : 'changed'),
            'subject_type' => 'Subscription',
            'subject_id' => $event->subscription->id ?? $event->newSubscription->id,
            'properties' => ['plan_id' => $event->subscription->plan_id ?? $event->newSubscription->plan_id],
        ]);
    }
}
