<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use Illuminate\Http\Request;

class SupplierStatementController extends Controller
{
    public function show(Request $request, $supplierId)
    {
        $supplier = Supplier::findOrFail($supplierId);

        $from = $request->query('from');
        $to   = $request->query('to');

        /*
         |------------------------------------------------
         | Opening balance
         | كل ما قبل from
         |------------------------------------------------
         */

        $openingQuery = SupplierLedgerEntry::where('supplier_id', $supplierId);

        if ($from) {
            $openingQuery->where('entry_date', '<', $from);
        }

        $openingDebit  = (clone $openingQuery)->sum('debit');
        $openingCredit = (clone $openingQuery)->sum('credit');

        $openingBalance = $openingDebit - $openingCredit;

        /*
         |------------------------------------------------
         | Entries inside period
         |------------------------------------------------
         */

        $entriesQuery = SupplierLedgerEntry::where('supplier_id', $supplierId);

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

        /*
         |------------------------------------------------
         | Running balance
         |------------------------------------------------
         */

        $running = $openingBalance;

        $rows = $entries->map(function ($row) use (&$running) {

            $running += ($row->debit - $row->credit);

            return [
                'id'         => $row->id,
                'entry_date' => $row->entry_date->toDateString(),
                'description' => $row->description,
                'type'       => $row->type,

                'debit'      => $row->debit,
                'credit'     => $row->credit,

                'balance'    => $running,

                'purchase_order_id'   => $row->purchase_order_id,
                'supplier_payment_id' => $row->supplier_payment_id,
            ];
        });

        $closingBalance = $running;

        return response()->json([
            'supplier' => [
                'id'    => $supplier->id,
                'name'  => $supplier->name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
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
