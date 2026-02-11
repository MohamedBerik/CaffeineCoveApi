<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AccountingService;
use App\Models\CustomerLedgerEntry;

class PaymentRefundController extends Controller
{
    public function refund(Request $request, Payment $payment)
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01']
        ]);

        $alreadyRefunded = $payment->refunds()->sum('amount');

        $remaining = $payment->amount - $alreadyRefunded;

        if ($request->amount > $remaining) {
            return response()->json([
                'msg' => 'Refund exceeds paid amount',
                'remaining' => $remaining
            ], 422);
        }

        return DB::transaction(function () use ($request, $payment) {

            $refund = $payment->refunds()->create([
                'amount'     => $request->amount,
                'refunded_at' => now(),
                'created_by' => $request->user()->id ?? null
            ]);

            CustomerLedgerEntry::create([
                'customer_id' => $payment->invoice->customer_id,
                'invoice_id'  => $payment->invoice_id,
                'payment_id'  => $payment->id,
                'refund_id'   => $refund->id,
                'type'        => 'refund',
                'debit'       => $refund->amount,
                'credit'      => 0,
                'entry_date'  => now(),
                'description' => 'Refund for payment #' . $payment->id,
            ]);


            $arAccount = Account::where('code', '1100')->firstOrFail();
            $cashAccount = Account::where('code', '1000')->firstOrFail();

            AccountingService::createEntry(
                $payment->invoice,
                'Refund for payment #' . $payment->id,
                [
                    [
                        'account_id' => $arAccount->id,
                        'debit'  => $request->amount,
                        'credit' => 0
                    ],
                    [
                        'account_id' => $cashAccount->id,
                        'debit'  => 0,
                        'credit' => $request->amount
                    ]
                ],
                $request->user()->id ?? null
            );


            $invoice = $payment->invoice->fresh();

            if ($invoice->net_paid <= 0) {
                $invoice->update(['status' => 'unpaid']);
            } elseif ($invoice->net_paid < $invoice->total) {
                $invoice->update(['status' => 'partially_paid']);
            } else {
                $invoice->update(['status' => 'paid']);
            }
            activity('payment.refunded', $payment, [
                'amount' => $request->amount
            ]);

            return response()->json([
                'msg' => 'Refund recorded',
                'refund_id' => $refund->id
            ]);
        });
    }
}
