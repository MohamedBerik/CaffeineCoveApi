<?php

namespace App\Events;

use App\Models\Subscription;
use App\Models\BillingInvoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }
}

class SubscriptionCancelled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }
}

// في BillingEvents.php
class SubscriptionChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $oldSubscription;
    public $newSubscription;

    public function __construct(Subscription $oldSubscription, Subscription $newSubscription)
    {
        $this->oldSubscription = $oldSubscription;
        $this->newSubscription = $newSubscription;
    }
}

class PaymentReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $invoice;

    public function __construct(BillingInvoice $invoice)
    {
        $this->invoice = $invoice;
    }
}
