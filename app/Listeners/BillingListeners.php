<?php

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Events\SubscriptionCancelled;
use App\Events\PaymentReceived;
use App\Events\SubscriptionChanged;
use App\Models\ActivityLog;

class ActivateCompany
{
    public function handle(SubscriptionCreated $event)
    {
        $event->subscription->company->update(['status' => 'active']);
    }
}
