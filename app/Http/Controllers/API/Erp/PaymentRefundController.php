<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerLedgerEntry;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AccountingService;

class PaymentRefundController extends Controller
{
    public function refund(Request $request, $paymentId)
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01']
        ]);

        $companyId = $request->user()->company_id;

        return DB::transaction(function () use ($request, $paymentId, $companyId) {

            // 1) lock payment
            $payment = Payment::where('company_id', $companyId)
                ->lockForUpdate()
                ->with(['refunds']) // refunds بس
                ->findOrFail($paymentId);

            // 2) fetch invoice explicitly (no eager-load ambiguity)
            $invoice = Invoice::where('company_id', $companyId)
                ->find($payment->invoice_id);

            if (!$invoice) {
                // Debug واضح: المشكلة غالبًا token شركة غلط أو payment invoice_id غلط
                return response()->json([
                    'msg' => 'Invoice not found for this payment',
                    'debug' => [
                        'request_company_id' => $companyId,
                        'payment_id' => $payment->id,
                        'payment_company_id' => $payment->company_id,
                        'payment_invoice_id' => $payment->invoice_id,
                    ]
                ], 422);
            }

            $alreadyRefunded = $payment->refunds->sum('amount');
            $remaining = $payment->amount - $alreadyRefunded;

            if ($request->amount > $remaining) {
                return response()->json([
                    'msg' => 'Refund exceeds paid amount',
                    'remaining' => $remaining
                ], 422);
            }

            // 3) create refund
            $refund = $payment->refunds()->create([
                'company_id'  => $companyId,
                'amount'      => $request->amount,
                'refunded_at' => now(),
                'created_by'  => $request->user()->id ?? null
            ]);

            // 4) customer ledger entry (refund = debit)
            CustomerLedgerEntry::create([
                'company_id'  => $companyId,
                'customer_id' => $invoice->customer_id,
                'invoice_id'  => $invoice->id,
                'payment_id'  => $payment->id,
                'refund_id'   => $refund->id,
                'type'        => 'refund',
                'debit'       => $refund->amount,
                'credit'      => 0,
                'entry_date'  => now()->toDateString(),
                'description' => 'Refund for payment #' . $payment->id,
            ]);

            // 5) accounts (correct codes)
            $cashAccount = Account::where('company_id', $companyId)
                ->where('code', '1000')
                ->firstOrFail();

            $arAccount = Account::where('company_id', $companyId)
                ->where('code', '1100')
                ->firstOrFail();

            // 6) accounting entry: Dr AR / Cr Cash
            AccountingService::createEntry(
                $invoice,
                'Refund for payment #' . $payment->id,
                [
                    [
                        'account_id' => $arAccount->id,
                        'debit'      => $refund->amount,
                        'credit'     => 0
                    ],
                    [
                        'account_id' => $cashAccount->id,
                        'debit'      => 0,
                        'credit'     => $refund->amount
                    ]
                ],
                $request->user()->id ?? null,
                now()->toDateString()
            );

            // 7) recalc invoice status (DB safe + avoid ambiguous amount)
            $paid = Payment::where('company_id', $companyId)
                ->where('invoice_id', $invoice->id)
                ->sum('amount');

            $refunded = DB::table('payment_refunds')
                ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
                ->where('payments.company_id', $companyId)
                ->where('payments.invoice_id', $invoice->id)
                ->sum('payment_refunds.amount'); // ✅ مهم لتجنب ambiguous

            $net = $paid - $refunded;

            if ($net <= 0) $status = 'unpaid';
            elseif ($net < $invoice->total) $status = 'partially_paid';
            else $status = 'paid';

            $invoice->update(['status' => $status]);

            activity('payment.refunded', $payment, [
                'amount' => $refund->amount,
                'refund_id' => $refund->id,
                'invoice_id' => $invoice->id,
            ], $companyId);

            return response()->json([
                'msg'            => 'Refund recorded',
                'refund_id'      => $refund->id,
                'invoice_status' => $status,
                'net_paid'       => $net,
            ]);
        });
    }
}
