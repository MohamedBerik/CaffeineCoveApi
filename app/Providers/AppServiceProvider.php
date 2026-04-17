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

class AppServiceProvider extends ServiceProvider
{
    // app/Providers/AppServiceProvider.php

    public function boot()
    {
        // ✅ علّق أي حاجة متعلقة بـ Tenant أو Cache مؤقتًا
    }
}
