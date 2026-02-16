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
        $companyId = $request->user()->company_id;

        // التأكد أن العميل تابع لنفس الشركة
        $customer = Customer::where('company_id', $companyId)
            ->findOrFail($customerId);

        $from = $request->query('from');
        $to   = $request->query('to');

        // -----------------------------------------
        // Opening balance
        // كل ما قبل from
        // -----------------------------------------
        $openingQuery = CustomerLedgerEntry::where('company_id', $companyId)
            ->where('customer_id', $customerId);

        if ($from) {
            $openingQuery->where('entry_date', '<', $from);
        }

        $openingDebit  = (clone $openingQuery)->sum('debit');
        $openingCredit = (clone $openingQuery)->sum('credit');

        $openingBalance = $openingDebit - $openingCredit;

        // -----------------------------------------
        // Entries inside period
        // -----------------------------------------
        $entriesQuery = CustomerLedgerEntry::where('company_id', $companyId)
            ->where('customer_id', $customerId);

        if ($from) {
            $entriesQuery->where('entry_date', '>=', $from);
        }

        if ($to) {
            $entriesQuery->where('entry_date', '<=', $to);
        }

        $entries = $entriesQuery
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        // -----------------------------------------
        // Running balance
        // -----------------------------------------
        $running = $openingBalance;

        $rows = $entries->map(function ($row) use (&$running) {

            $running += ($row->debit - $row->credit);

            return [
                'id'          => $row->id,
                'entry_date'  => $row->entry_date->toDateString(),
                'description' => $row->description,
                'type'        => $row->type,

                'debit'       => $row->debit,
                'credit'      => $row->credit,

                'balance'     => $running,

                'invoice_id'  => $row->invoice_id,
                'payment_id'  => $row->payment_id,
                'refund_id'   => $row->refund_id,
            ];
        });

        // -----------------------------------------
        // Closing balance
        // -----------------------------------------
        $closingBalance = $running;

        return response()->json([
            'customer' => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'code'  => $customer->code ?? null,
                'phone' => $customer->phone ?? null,
                'email' => $customer->email ?? null,
            ],

            'period' => [
                'from' => $from ?? 'Beginning',
                'to'   => $to   ?? now()->toDateString(),
            ],

            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,

            'entries' => $rows
        ]);
    }
}
