<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use Illuminate\Http\Request;

class CustomerStatementController extends Controller
{
    public function show(Request $request, $customerId)
    {
        $customer = Customer::findOrFail($customerId);

        // لو لم يرسل تاريخ
        $from = $request->get('from');
        $to   = $request->get('to');

        if (!$from) {
            $from = now()->startOfMonth()->toDateString();
        }

        if (!$to) {
            $to = now()->toDateString();
        }

        /*
        |--------------------------------------------------------------------------
        | Opening balance (قبل from)
        |--------------------------------------------------------------------------
        */
        $openingBalance = CustomerLedgerEntry::where('customer_id', $customer->id)
            ->where('entry_date', '<', $from)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as balance')
            ->value('balance');

        $openingBalance = $openingBalance ?? 0;

        /*
        |--------------------------------------------------------------------------
        | Entries داخل الفترة
        |--------------------------------------------------------------------------
        */
        $entries = CustomerLedgerEntry::where('customer_id', $customer->id)
            ->whereBetween('entry_date', [
                $from . ' 00:00:00',
                $to   . ' 23:59:59'
            ])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | running balance
        |--------------------------------------------------------------------------
        */
        $running = $openingBalance;

        $entries = $entries->map(function ($row) use (&$running) {

            $running += ($row->debit - $row->credit);

            return [
                'id'          => $row->id,
                'date'        => $row->entry_date,
                'description' => $row->description,
                'debit'       => $row->debit,
                'credit'      => $row->credit,
                'balance'     => $running,

                'type'        => $row->type,
                'invoice_id'  => $row->invoice_id,
                'payment_id'  => $row->payment_id,
                'refund_id'   => $row->refund_id,
            ];
        });

        return response()->json([
            'customer' => [
                'id'      => $customer->id,
                'name'    => $customer->name ?? null,
                'code'    => $customer->code ?? null,
                'phone'   => $customer->phone ?? null,
                'address' => $customer->address ?? null,
            ],

            'period' => [
                'from' => $from,
                'to'   => $to,
            ],

            'opening_balance' => $openingBalance,

            'entries' => $entries,
        ]);
    }
}
