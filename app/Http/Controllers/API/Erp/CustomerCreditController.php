<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerCreditController extends Controller
{
    public function show(Request $request, $customerId)
    {
        $companyId = $request->user()->company_id;

        // تأكد إن العميل تابع لنفس الشركة
        Customer::where('company_id', $companyId)->findOrFail($customerId);

        $base = DB::table('customer_credits')
            ->where('company_id', $companyId)
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
                'credit_issued' => $creditIssued, // إجمالي credits created
                'credit_used'   => $creditUsed,   // إجمالي debits (credits applied/refunded… حسب تصميمك)
                'net_credit'    => $netCredit,    // الرصيد الحالي
            ],
        ]);
    }
}
