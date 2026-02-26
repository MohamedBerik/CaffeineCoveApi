<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CustomerStatementController extends Controller
{
    public function show(Request $request, $customerId)
    {
        $companyId = $request->user()->company_id;

        // Ensure customer belongs to tenant
        $customer = Customer::where('company_id', $companyId)->findOrFail($customerId);

        // Parse filters safely (date only)
        $from = $request->query('from'); // expected: YYYY-MM-DD
        $to   = $request->query('to');   // expected: YYYY-MM-DD

        $fromDate = $from ? Carbon::parse($from)->toDateString() : null;
        $toDate   = $to   ? Carbon::parse($to)->toDateString()   : null;

        /*
         |---------------------------------------------------------
         | Opening balance = all entries BEFORE fromDate
         |---------------------------------------------------------
         */
        $openingQuery = CustomerLedgerEntry::where('company_id', $companyId)
            ->where('customer_id', $customerId);

        if ($fromDate) {
            // whereDate works whether entry_date is DATE or DATETIME/TIMESTAMP
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
        $entriesQuery = CustomerLedgerEntry::where('company_id', $companyId)
            ->where('customer_id', $customerId);

        if ($fromDate) {
            $entriesQuery->whereDate('entry_date', '>=', $fromDate);
        }

        if ($toDate) {
            $entriesQuery->whereDate('entry_date', '<=', $toDate);
        }

        // IMPORTANT: stable ordering (oldest -> newest)
        $entries = $entriesQuery
            ->orderBy('entry_date', 'asc')
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

            // entry_date might be Carbon OR string depending on casts
            $entryCarbon = $row->entry_date instanceof Carbon
                ? $row->entry_date
                : Carbon::parse($row->entry_date);

            return [
                'id'            => $row->id,
                'entry_date'    => $entryCarbon->toDateString(),
                // optional (helpful for debugging / future UI)
                'entry_datetime' => $entryCarbon->toISOString(),

                'description'   => $row->description,
                'type'          => $row->type,

                'debit'         => number_format($debit, 2, '.', ''),
                'credit'        => number_format($credit, 2, '.', ''),
                'balance'       => (float) $running,

                'invoice_id'    => $row->invoice_id,
                'payment_id'    => $row->payment_id,
                'refund_id'     => $row->refund_id,
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
