<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Observers\DashboardObserver;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Appointment::observe(DashboardObserver::class);
        Invoice::observe(DashboardObserver::class);
        Payment::observe(DashboardObserver::class);

        // تجنب الاتصال بقاعدة البيانات أثناء البناء (deployment)
        // if ($this->app->runningInConsole() && !$this->app->environment('production')) {
        //     return;
        // }

        try {
            if (class_exists(\Doctrine\DBAL\Types\Type::class)) {
                Schema::getConnection()
                    ->getDoctrineSchemaManager()
                    ->getDatabasePlatform()
                    ->registerDoctrineTypeMapping('enum', 'string');
            }
        } catch (\Exception $e) {
            // تجاهل الخطأ أثناء البناء أو إذا كانت قاعدة البيانات مش شغالة
            Log::info('Database not available for Doctrine mapping: ' . $e->getMessage());
        }
    }
}
