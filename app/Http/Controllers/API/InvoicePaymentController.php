<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AccountingService;
use App\Models\Account;

class InvoicePaymentController extends Controller
{
    public function pay(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,card,bank']
        ]);

        if ($invoice->status === 'paid') {
            return response()->json([
                'msg' => 'Invoice already paid'
            ], 422);
        }

        $alreadyPaid = $invoice->payments->sum('amount');
        $refunded = $invoice->refunds()->sum('payment_refunds.amount');
        $netPaid = $alreadyPaid - $refunded;
        $remaining = $invoice->total - $netPaid;

        if ($remaining <= 0) {
            return response()->json([
                'msg' => 'No remaining amount for this invoice'
            ], 422);
        }

        if ($request->amount > $remaining) {
            return response()->json([
                'msg' => 'Payment exceeds remaining amount',
                'remaining' => $remaining
            ], 422);
        }

        return DB::transaction(function () use ($invoice, $request, $remaining) {

            $payment = Payment::create([
                'invoice_id'  => $invoice->id,
                'amount'      => $request->amount,
                'method'      => $request->method,
                'paid_at'     => now(),
                'received_by' => $request->user()->id ?? null
            ]);

            $totalPaid = $invoice->payments()->sum('amount');
            $totalRefunded = $invoice->refunds()->sum('payment_refunds.amount');

            $netPaid = $totalPaid - $totalRefunded;

            $invoice->update([
                'status' => $netPaid >= $invoice->total
                    ? 'paid'
                    : ($netPaid > 0 ? 'partial' : 'unpaid')
            ]);


            $cashAccount  = Account::where('code', '1000')->firstOrFail();
            $salesAccount = Account::where('code', '4000')->firstOrFail();

            // ✅ تمرير source_type و source_id لتجنب خطأ الـ journal_entries
            AccountingService::createEntry(
                $invoice,
                'Invoice payment #' . $invoice->id,
                [
                    [
                        'account_id' => $cashAccount->id,
                        'debit'      => $request->amount,
                        'credit'     => 0
                    ],
                    [
                        'account_id' => $salesAccount->id,
                        'debit'      => 0,
                        'credit'     => $request->amount
                    ],
                ],
                $request->user()->id ?? null,
                now()->toDateString()   // ✅ date
            );

            activity('invoice.paid', $invoice, [
                'amount' => $request->amount
            ]);

            return response()->json([
                'msg' => 'Payment recorded',
                'invoice_status' => $invoice->fresh()->status,
                'payment_id' => $payment->id
            ]);
        });
    }
    public function refund(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,card,bank']
        ]);

        $totalPaid = $invoice->payments->sum('amount');
        $totalRefunded = $invoice->refunds()->sum('payment_refunds.amount');
        $remainingRefundable = $totalPaid - $totalRefunded;

        if ($remainingRefundable <= 0) {
            return response()->json([
                'msg' => 'No refundable amount for this invoice'
            ], 422);
        }

        if ($request->amount > $remainingRefundable) {
            return response()->json([
                'msg' => 'Refund exceeds paid amount',
                'remaining' => $remainingRefundable
            ], 422);
        }

        return DB::transaction(function () use ($invoice, $request) {

            $refund = $invoice->refunds()->create([
                'amount' => $request->amount,
                'method' => $request->method,
                'refunded_by' => $request->user()->id ?? null,
                'refunded_at' => now()
            ]);

            // تحديث حالة الفاتورة
            $netPaid = $totalPaid - ($totalRefunded + $request->amount);

            $invoice->update([
                'status' => $netPaid >= $invoice->total
                    ? 'paid'
                    : ($netPaid > 0 ? 'partial' : 'unpaid')
            ]);

            // قيود محاسبية عكسية
            $cashAccount  = Account::where('code', '1000')->firstOrFail();
            $salesAccount = Account::where('code', '4000')->firstOrFail();

            AccountingService::createEntry(
                $invoice,
                'Invoice refund #' . $invoice->id,
                [
                    [
                        'account_id' => $salesAccount->id,
                        'debit'      => $request->amount,
                        'credit'     => 0
                    ],
                    [
                        'account_id' => $cashAccount->id,
                        'debit'      => 0,
                        'credit'     => $request->amount
                    ],
                ],
                $request->user()->id ?? null,
                now()->toDateString()
            );

            activity('invoice.refunded', $invoice, [
                'amount' => $request->amount
            ]);

            return response()->json([
                'msg' => 'Refund recorded',
                'invoice_status' => $invoice->fresh()->status,
                'refund_id' => $refund->id
            ]);
        });
    }
}
