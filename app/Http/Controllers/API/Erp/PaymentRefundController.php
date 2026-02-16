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
    public function refund(Request $request, $paymentId)
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01']
        ]);

        $companyId = $request->user()->company_id;

        $payment = Payment::where('company_id', $companyId)
            ->with([
                'invoice' => function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                },
                'refunds' => function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                }
            ])
            ->findOrFail($paymentId);

        $alreadyRefunded = $payment->refunds
            ->sum('amount');

        $remaining = $payment->amount - $alreadyRefunded;

        if ($request->amount > $remaining) {
            return response()->json([
                'msg' => 'Refund exceeds paid amount',
                'remaining' => $remaining
            ], 422);
        }

        return DB::transaction(function () use ($request, $payment, $companyId) {

            $refund = $payment->refunds()->create([
                'company_id'  => $companyId,
                'amount'      => $request->amount,
                'refunded_at' => now(),
                'created_by'  => $request->user()->id ?? null
            ]);

            CustomerLedgerEntry::create([
                'company_id'  => $companyId,
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

            $arAccount = Account::where('company_id', $companyId)
                ->where('code', '1000')
                ->firstOrFail();

            $cashAccount = Account::where('company_id', $companyId)
                ->where('code', '1000')
                ->firstOrFail();

            /*
             |--------------------------------------------------------------------------
             | Accounting entry
             |--------------------------------------------------------------------------
             */

            $entry = AccountingService::createEntry(
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

            /*
             |--------------------------------------------------------------------------
             | ربط القيد المحاسبي بالشركة (لأن AccountingService لا يمرر company_id)
             |--------------------------------------------------------------------------
             */

            if ($entry) {
                $entry->update([
                    'company_id' => $companyId
                ]);

                $entry->lines()->update([
                    'company_id' => $companyId
                ]);
            }

            /*
             |--------------------------------------------------------------------------
             | Recalculate invoice status (آمن multi-tenant)
             |--------------------------------------------------------------------------
             */

            $invoice = $payment->invoice->fresh();

            $paid = $invoice->payments()
                ->where('company_id', $companyId)
                ->sum('amount');

            $refunded = $invoice->payments()
                ->where('company_id', $companyId)
                ->withSum(
                    ['refunds as refunded_amount' => function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    }],
                    'amount'
                )
                ->get()
                ->sum('refunded_amount');

            $net = $paid - $refunded;

            if ($net <= 0) {
                $status = 'unpaid';
            } elseif ($net < $invoice->total) {
                $status = 'partially_paid';
            } else {
                $status = 'paid';
            }

            $invoice->update([
                'status' => $status
            ]);

            activity('payment.refunded', $payment, [
                'amount' => $request->amount
            ]);

            return response()->json([
                'msg' => 'Refund recorded',
                'refund_id' => $refund->id,
                'invoice_status' => $status
            ]);
        });
    }
}
