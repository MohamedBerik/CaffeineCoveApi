<?php

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Events\SubscriptionCancelled;
use App\Events\PaymentReceived;
use App\Models\ActivityLog;

class ActivateCompany
{
    public function handle(SubscriptionCreated $event)
    {
        $event->subscription->company->update(['status' => 'active']);
    }
}

class LogSubscriptionActivity
{
    public function onSubscriptionCreated(SubscriptionCreated $event)
    {
        ActivityLog::create([
            'company_id' => $event->subscription->company_id,
            'user_id' => auth()->id(),
            'action' => 'subscription.created',
            'subject_type' => get_class($event->subscription),
            'subject_id' => $event->subscription->id,
            'properties' => [
                'plan_id' => $event->subscription->plan_id,
                'amount' => $event->subscription->amount,
                'billing_cycle' => $event->subscription->billing_cycle,
            ],
        ]);
    }

    public function onSubscriptionCancelled(SubscriptionCancelled $event)
    {
        ActivityLog::create([
            'company_id' => $event->subscription->company_id,
            'user_id' => auth()->id(),
            'action' => 'subscription.cancelled',
            'subject_type' => get_class($event->subscription),
            'subject_id' => $event->subscription->id,
            'properties' => [
                'plan_id' => $event->subscription->plan_id,
                'cancelled_at' => now(),
            ],
        ]);
    }

    public function onPaymentReceived(PaymentReceived $event)
    {
        ActivityLog::create([
            'company_id' => $event->invoice->company_id,
            'user_id' => auth()->id(),
            'action' => 'payment.received',
            'subject_type' => get_class($event->invoice),
            'subject_id' => $event->invoice->id,
            'properties' => [
                'invoice_number' => $event->invoice->number,
                'amount' => $event->invoice->amount,
            ],
        ]);
    }
}
