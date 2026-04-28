<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    // public function register(): void
    // {
    //     Telescope::night();

    //     $this->hideSensitiveRequestDetails();

    //     // ✅ تسجيل فقط في البيئات المسموحة
    //     Telescope::filter(function (IncomingEntry $entry) {
    //         if ($this->app->environment('local', 'staging')) {
    //             return true;
    //         }

    //         // ✅ في الإنتاج: سجل الأخطاء فقط + الطلبات البطيئة
    //         return $entry->isReportableException() ||
    //             $entry->isFailedRequest() ||
    //             $entry->isSlowQuery() ||
    //             $entry->isFailedJob() ||
    //             $entry->type === 'exception' ||
    //             ($entry->type === 'request' && $entry->content['response_status'] >= 500);
    //     });
    // }

    public function register()
    {
        if ($this->app->environment('production')) {
            $this->app->singleton('telescope', function () {
                return new class {
                    public function __call($method, $args)
                    {
                        return $this;
                    }
                };
            });
        }
    }
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local', 'staging')) {
            return;
        }

        Telescope::hideRequestParameters([
            'password',
            'password_confirmation',
            'token',
            'api_key',
            'secret',
        ]);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            return $user && $user->is_super_admin;
        });
    }
}
