<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\TreatmentPlan;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Observers\DashboardObserver;
use App\Services\Tenant;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

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

        $this->app['queue']->createPayloadUsing(function ($connection, $queue, $payload) {
            return ['tenant_middleware' => true];
        });
        Queue::after(function () {
            Tenant::reset();
        });
    }

    /**
     * Register model observers
     */
    protected function registerObservers(): void
    {
        Appointment::observe(DashboardObserver::class);
        Invoice::observe(DashboardObserver::class);
        Payment::observe(DashboardObserver::class);
        Customer::observe(DashboardObserver::class);
        TreatmentPlan::observe(DashboardObserver::class);
        Order::observe(DashboardObserver::class);
        PurchaseOrder::observe(DashboardObserver::class);
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
