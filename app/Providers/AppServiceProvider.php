<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\TreatmentPlan;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Observers\ActivityLogObserver;
use App\Observers\DashboardObserver;
use App\Services\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\Doctor;
use App\Models\DentalRecord;
use App\Models\Employee;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Procedure;
use App\Models\PatientRadiology;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ✅ Register Tenant Service as singleton
        $this->app->singleton(Tenant::class, function () {
            return new Tenant();
        });

        // ✅ Register helper functions
        $this->registerHelpers();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ✅ Register observers
        $this->registerObservers();

        // ✅ Configure Doctrine for ENUM support
        $this->configureDoctrineEnumSupport();

        // ✅ Set database strict mode
        $this->configureDatabaseStrictMode();

        // ✅ Register custom validation rules
        $this->registerValidationRules();

        // ✅ Configure cache for multi-tenant
        $this->configureTenantCache();

        // ✅ Queue tenant reset
        $this->configureQueueTenantReset();

        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            if ($user) {
                return Limit::perMinute(120)->by($user->id);
            }
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email') ?: $request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('billing', function (Request $request) {
            $user = $request->user();
            if ($user) {
                return Limit::perMinute(10)->by($user->id);
            }
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('payment', function (Request $request) {
            $user = $request->user();
            if ($user) {
                return Limit::perMinute(3)->by($user->id);
            }
            return Limit::perMinute(1)->by($request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
        // ✅ Structured Logging for API Requests
        if (app()->environment('production')) {
            $this->app['router']->matched(function ($route) {
                // ✅ Login/Register Bypass
                if (request()->is('api/login') || request()->is('api/register')) {
                    return;
                }

                // ✅ طريقة آمنة لجلب اسم الـ route في Laravel 8
                $routeName = request()->path();

                Log::channel('api')->info('API Request', [
                    'route' => $routeName,
                    'method' => request()->method(),
                    'url' => request()->fullUrl(),
                    'user_id' => auth()->id(),
                    'tenant_id' => \App\Services\Tenant::id(),
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });
        }
    }

    /**
     * Register model observers
     */
    protected function registerObservers(): void
    {
        // ✅ Models that need Activity Logging
        $loggableModels = [
            Product::class,
            Invoice::class,
            Customer::class,
            TreatmentPlan::class,
            Order::class,
            PurchaseOrder::class,
            Payment::class,
            Doctor::class,
            DentalRecord::class,
            Employee::class,
            Category::class,
            Supplier::class,
            Procedure::class,
            PatientRadiology::class,
            User::class,

        ];

        foreach ($loggableModels as $model) {
            $model::observe(ActivityLogObserver::class);
        }

        // ✅ Models that trigger Dashboard cache invalidation
        $dashboardModels = [
            Appointment::class,
            Invoice::class,
            Payment::class,
            Customer::class,
            TreatmentPlan::class,
            Order::class,
            PurchaseOrder::class,
        ];

        foreach ($dashboardModels as $model) {
            $model::observe(DashboardObserver::class);
        }
    }

    /**
     * Configure Doctrine ENUM support
     */
    protected function configureDoctrineEnumSupport(): void
    {
        try {
            if (class_exists(\Doctrine\DBAL\Types\Type::class)) {
                Schema::getConnection()
                    ->getDoctrineSchemaManager()
                    ->getDatabasePlatform()
                    ->registerDoctrineTypeMapping('enum', 'string');
            }
        } catch (\Exception $e) {
            Log::info('Database not available for Doctrine mapping: ' . $e->getMessage());
        }
    }

    /**
     * Configure database strict mode
     */
    protected function configureDatabaseStrictMode(): void
    {
        try {
            if (app()->environment('local', 'development')) {
                DB::statement("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            }
        } catch (\Exception $e) {
            Log::warning('Failed to set database strict mode: ' . $e->getMessage());
        }
    }

    /**
     * Register custom validation rules
     */
    protected function registerValidationRules(): void
    {
        // ✅ Phone number validation
        \Illuminate\Support\Facades\Validator::extend('phone', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[0-9+\-\s()]+$/', $value);
        }, 'The :attribute must be a valid phone number.');

        // ✅ Egyptian phone number validation
        \Illuminate\Support\Facades\Validator::extend('egyptian_phone', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^(?:\+20|0)?1[0125][0-9]{8}$/', preg_replace('/[^0-9+]/', '', $value));
        }, 'The :attribute must be a valid Egyptian phone number.');

        // ✅ Patient code validation
        \Illuminate\Support\Facades\Validator::extend('patient_code', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^PT-\d{5}$/', $value);
        }, 'The :attribute must be in format PT-XXXXX.');
    }

    /**
     * Configure cache for multi-tenant
     */
    protected function configureTenantCache(): void
    {
        // ✅ Add tenant prefix to cache keys
        Cache::macro('tenantRemember', function ($key, $ttl, $callback) {
            $tenantId = Tenant::id() ?? 'global';
            $tenantKey = "tenant_{$tenantId}_{$key}";
            return Cache::remember($tenantKey, $ttl, $callback);
        });

        Cache::macro('tenantRememberForever', function ($key, $callback) {
            $tenantId = Tenant::id() ?? 'global';
            $tenantKey = "tenant_{$tenantId}_{$key}";
            return Cache::rememberForever($tenantKey, $callback);
        });

        Cache::macro('tenantForget', function ($key) {
            $tenantId = Tenant::id() ?? 'global';
            $tenantKey = "tenant_{$tenantId}_{$key}";
            return Cache::forget($tenantKey);
        });
    }

    /**
     * Configure queue tenant reset
     */
    protected function configureQueueTenantReset(): void
    {
        $this->app['queue']->createPayloadUsing(function ($connection, $queue, $payload) {
            return ['tenant_middleware' => true];
        });

        Queue::after(function () {
            Tenant::reset();
        });
    }

    /**
     * Register helper functions
     */
    protected function registerHelpers(): void
    {
        // ✅ Tenant cache helper
        if (!function_exists('tenant_cache_key')) {
            function tenant_cache_key(string $key): string
            {
                $tenantId = \App\Services\Tenant::id() ?? 'global';
                return "tenant_{$tenantId}_{$key}";
            }
        }

        // ✅ Format currency helper
        if (!function_exists('format_currency')) {
            function format_currency($amount, string $currency = 'EGP'): string
            {
                return number_format((float) $amount, 2) . ' ' . $currency;
            }
        }

        // ✅ Format date helper
        if (!function_exists('format_date')) {
            function format_date($date, string $format = 'Y-m-d'): string
            {
                if (!$date) {
                    return '';
                }
                return \Carbon\Carbon::parse($date)->format($format);
            }
        }

        // ✅ Format time helper
        if (!function_exists('format_time')) {
            function format_time($time, string $format = 'H:i'): string
            {
                if (!$time) {
                    return '';
                }
                return \Carbon\Carbon::parse($time)->format($format);
            }
        }
    }
}
