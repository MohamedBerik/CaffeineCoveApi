<?php

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ActivateCompany
{
    public function handle(SubscriptionCreated $event)
    {
        $subscription = $event->subscription;
        $company = $subscription->company;

        if ($company && $company->status === 'trial') {
            $company->update(['status' => 'active']);
        }
    }
}
