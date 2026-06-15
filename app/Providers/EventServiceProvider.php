<?php

namespace App\Providers;

use App\Observers\ActivityLogObserver;
use App\Services\Tenant;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobProcessed;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \App\Events\SubscriptionCreated::class => [
            \App\Listeners\ActivateCompany::class,
            \App\Listeners\LogSubscriptionActivity::class . '@onSubscriptionCreated',
        ],
        \App\Events\SubscriptionCancelled::class => [
            \App\Listeners\LogSubscriptionActivity::class . '@onSubscriptionCancelled',
        ],
        \App\Events\PaymentReceived::class => [
            \App\Listeners\LogSubscriptionActivity::class . '@onPaymentReceived',
        ],

        \App\Events\SubscriptionChanged::class => [
            \App\Listeners\LogSubscriptionActivity::class . '@onSubscriptionChanged',
        ],
        \App\Events\FailedLogin::class => [
            \App\Listeners\LogFailedLogin::class,
        ],
        \App\Events\SuspiciousActivity::class => [
            \App\Listeners\LogSuspiciousActivity::class,
        ],
        \App\Events\AdminOverride::class => [
            \App\Listeners\LogAdminOverride::class,
        ],
        \App\Events\SystemExceptionOccurred::class => [
            \App\Listeners\LogSystemException::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
        Event::listen(JobProcessed::class, function () {
            Tenant::reset();
        });
    }


    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
