<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CustomerStatementController extends Controller
{
    public function show(Request $request, $customerId)
    {
        // Ensure customer belongs to tenant (الـ Scope هيتأكد)
        $customer = Customer::query()->findOrFail($customerId);

        // Parse filters safely
        $from = $request->query('from');
        $to   = $request->query('to');

        $fromDate = $from ? Carbon::parse($from)->toDateString() : null;
        $toDate   = $to   ? Carbon::parse($to)->toDateString()   : null;

        /*
         |---------------------------------------------------------
         | Opening balance = all entries BEFORE fromDate
         |---------------------------------------------------------
         */
        $openingQuery = CustomerLedgerEntry::query()
            ->where('customer_id', $customerId);

        if ($fromDate) {
            $openingQuery->whereDate('entry_date', '<', $fromDate);
        }

        $openingDebit  = (float) (clone $openingQuery)->sum('debit');
        $openingCredit = (float) (clone $openingQuery)->sum('credit');
        $openingBalance = $openingDebit - $openingCredit;

        /*
         |---------------------------------------------------------
         | Entries within period
         |---------------------------------------------------------
         */
        $entriesQuery = CustomerLedgerEntry::query()
            ->where('customer_id', $customerId);

        if ($fromDate) {
            $entriesQuery->whereDate('entry_date', '>=', $fromDate);
        }

        if ($toDate) {
            $entriesQuery->whereDate('entry_date', '<=', $toDate);
        }

        $entries = $entriesQuery
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        /*
         |---------------------------------------------------------
         | Running balance
         |---------------------------------------------------------
         */
        $running = $openingBalance;

        $rows = $entries->map(function ($row) use (&$running) {
            $debit  = (float) $row->debit;
            $credit = (float) $row->credit;

            $running += ($debit - $credit);

            $entryDate = $row->entry_date instanceof Carbon
                ? $row->entry_date->toDateString()
                : Carbon::parse($row->entry_date)->toDateString();

            $entryDateTime = $row->created_at
                ? $row->created_at->toISOString()
                : null;

            return [
                'id'             => $row->id,
                'entry_date'     => $entryDate,
                'entry_datetime' => $entryDateTime,

                'description'    => $row->description,
                'type'           => $row->type,

                'debit'          => number_format($debit, 2, '.', ''),
                'credit'         => number_format($credit, 2, '.', ''),
                'balance'        => (float) $running,

                'invoice_id'     => $row->invoice_id,
                'payment_id'     => $row->payment_id,
                'refund_id'      => $row->refund_id,
            ];
        });

        $closingBalance = (float) $running;

        return response()->json([
            'customer' => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'code'  => $customer->code ?? null,
                'phone' => $customer->phone ?? null,
                'email' => $customer->email ?? null,
            ],
            'period' => [
                'from' => $fromDate ?? 'Beginning',
                'to'   => $toDate   ?? now()->toDateString(),
            ],
            'opening_balance' => (float) $openingBalance,
            'closing_balance' => (float) $closingBalance,
            'entries' => $rows->values(),
        ]);
    }
}
