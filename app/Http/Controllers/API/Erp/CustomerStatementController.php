<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\CustomerLedgerEntry;

class CustomerStatementController extends Controller
{
    public function statement($customerId)
    {
        $entries = CustomerLedgerEntry::where('customer_id', $customerId)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $balance = 0;

        $rows = $entries->map(function ($row) use (&$balance) {

            $balance += ($row->debit - $row->credit);

            return [
                'date'        => $row->entry_date,
                'description' => $row->description,
                'debit'       => (float) $row->debit,
                'credit'      => (float) $row->credit,
                'balance'     => round($balance, 2),

                // للربط في الواجهة
                'invoice_id'  => $row->invoice_id,
                'payment_id'  => $row->payment_id,
                'refund_id'   => $row->refund_id,
            ];
        });

        return response()->json([
            'customer_id' => $customerId,
            'opening_balance' => 0,
            'rows' => $rows
        ]);
    }
}
