<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    // app/Providers/EventServiceProvider.php

    protected $listen = [
        // ✅ علّق كل الـ Listeners مؤقتًا
    ];
}
