<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemAlert;
use App\Services\InsightService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Services\Tenant; // ✅ استخدام Tenant


class ErpDashboardController extends Controller
{
    // app/Http/Controllers/API/Erp/ErpDashboardController.php

    public function index(Request $request)
    {
        return response()->json(['msg' => 'ERP dashboard works!', 'status' => 200]);
    }
}
