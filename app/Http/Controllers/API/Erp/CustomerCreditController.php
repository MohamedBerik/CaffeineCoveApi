<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerCreditController extends Controller
{
    public function show(Request $request, $customerId)
    {
        // تأكد إن العميل تابع لنفس الشركة (الـ Scope هيتأكد)
        Customer::query()->findOrFail($customerId);

        $base = DB::table('customer_credits')
            ->where('customer_id', $customerId);

        $creditIssued = (float) (clone $base)
            ->where('type', 'credit')
            ->sum('amount');

        $creditUsed = (float) (clone $base)
            ->where('type', 'debit')
            ->sum('amount');

        $netCredit = $creditIssued - $creditUsed;

        return response()->json([
            'msg' => 'Customer credit balance',
            'status' => 200,
            'data' => [
                'customer_id'   => (int) $customerId,
                'credit_issued' => $creditIssued,
                'credit_used'   => $creditUsed,
                'net_credit'    => $netCredit,
            ],
        ]);
    }
}
