<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Procedure;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    // app/Providers/AuthServiceProvider.php

    public function boot()
    {
        $this->registerPolicies();

        // ✅ علّق أي Gates أو Policies مؤقتًا
        // Gate::before(...)
    }
}
