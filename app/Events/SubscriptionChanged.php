<?php

namespace App\Events;

use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionChanged
{
    use Dispatchable, SerializesModels;

    /** @var Subscription */
    public $oldSubscription;
    /** @var Subscription */
    public $newSubscription;

    public function __construct(Subscription $oldSubscription, Subscription $newSubscription)
    {
        $this->oldSubscription = $oldSubscription;
        $this->newSubscription = $newSubscription;
    }
}
