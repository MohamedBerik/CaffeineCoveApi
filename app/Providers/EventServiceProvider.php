<?php

namespace App\Providers;

use App\Events\AlertCreated;
use App\Events\AppointmentCompleted;
use App\Events\AppointmentCreated;
use App\Events\AppointmentUpdated;
use App\Events\DashboardUpdated;
use App\Events\InsightGenerated;
use App\Events\InvoicePaid;
use App\Events\PaymentReceived;
use App\Events\StockLow;
use App\Listeners\SendAppointmentNotifications;
use App\Listeners\SendInvoiceNotifications;
use App\Listeners\SendPaymentNotifications;
use App\Listeners\SendStockAlerts;
use App\Listeners\UpdateDashboardCache;
use App\Listeners\GenerateInsights;
use App\Listeners\LogUserActivity;
use App\Listeners\BroadcastAlert;
use App\Listeners\BroadcastDashboardUpdate;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Authentication events
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Login::class => [
            LogUserActivity::class,
        ],
        Logout::class => [
            LogUserActivity::class,
        ],

        // Appointment events
        AppointmentCreated::class => [
            SendAppointmentNotifications::class,
            UpdateDashboardCache::class,
            LogUserActivity::class,
        ],
        AppointmentUpdated::class => [
            SendAppointmentNotifications::class,
            UpdateDashboardCache::class,
            LogUserActivity::class,
        ],
        AppointmentCompleted::class => [
            UpdateDashboardCache::class,
            GenerateInsights::class,
            LogUserActivity::class,
        ],

        // Financial events
        PaymentReceived::class => [
            SendPaymentNotifications::class,
            UpdateDashboardCache::class,
            GenerateInsights::class,
            LogUserActivity::class,
        ],
        InvoicePaid::class => [
            SendInvoiceNotifications::class,
            UpdateDashboardCache::class,
            GenerateInsights::class,
            LogUserActivity::class,
        ],

        // Dashboard & Insights
        DashboardUpdated::class => [
            BroadcastDashboardUpdate::class,
        ],
        InsightGenerated::class => [
            // Store insight in cache/database
        ],

        // Alerts
        AlertCreated::class => [
            BroadcastAlert::class,
        ],

        // Inventory events
        StockLow::class => [
            SendStockAlerts::class,
            LogUserActivity::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [
        // Event subscribers
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();

        // ✅ Register dynamic events
        $this->registerModelEvents();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }

    /**
     * Register model-specific events
     */
    protected function registerModelEvents(): void
    {
        // ✅ Appointment model events
        \App\Models\Appointment::created(function ($appointment) {
            event(new AppointmentCreated($appointment));
        });

        \App\Models\Appointment::updated(function ($appointment) {
            if ($appointment->isDirty('status')) {
                if ($appointment->status === 'completed') {
                    event(new AppointmentCompleted($appointment));
                }
            }
            event(new AppointmentUpdated($appointment));
        });

        // ✅ Payment model events
        \App\Models\Payment::created(function ($payment) {
            event(new PaymentReceived($payment));
        });

        // ✅ Invoice model events
        \App\Models\Invoice::updated(function ($invoice) {
            if ($invoice->isDirty('status') && $invoice->status === 'paid') {
                event(new InvoicePaid($invoice));
            }
        });

        // ✅ Product model events (low stock alert)
        \App\Models\Product::updated(function ($product) {
            if ($product->isDirty('stock_quantity')) {
                if ($product->stock_quantity <= 10 && $product->stock_quantity > 0) {
                    event(new StockLow($product));
                }
            }
        });
    }

    /**
     * Register custom event listeners
     */
    protected function registerCustomListeners(): void
    {
        // ✅ Listen to all model events for activity logging
        Event::listen('eloquent.*', function ($eventName, $data) {
            if (in_array($eventName, ['eloquent.retrieved', 'eloquent.saved'])) {
                return;
            }

            // Activity logging is handled by individual listeners
        });

        // ✅ Cache invalidation on model changes
        $models = ['Appointment', 'Invoice', 'Payment', 'Customer', 'TreatmentPlan'];

        foreach ($models as $model) {
            Event::listen("eloquent.saved: App\\Models\\{$model}", function () {
                // Invalidate dashboard cache
                $companyId = \App\Services\Tenant::id();
                if ($companyId) {
                    \Illuminate\Support\Facades\Cache::forget("dashboard_{$companyId}");
                }
            });
        }
    }
}
