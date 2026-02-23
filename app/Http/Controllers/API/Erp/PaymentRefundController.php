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
            'amount'     => ['required', 'numeric', 'min:0.01'],
            'applies_to' => ['nullable', 'in:invoice,credit'], // ✅ جديد
        ]);

        $companyId  = $request->user()->company_id;
        $appliesTo  = $request->input('applies_to', 'invoice'); // default invoice
        $amountReq  = (float) $request->amount;

        return DB::transaction(function () use ($request, $paymentId, $companyId, $appliesTo, $amountReq) {

            // 1) lock payment
            $payment = Payment::where('company_id', $companyId)
                ->lockForUpdate()
                ->with(['refunds']) // refunds كلها
                ->findOrFail($paymentId);

            // 2) invoice (مطلوبة فقط لو invoice refund)
            $invoice = null;
            if ($payment->invoice_id) {
                $invoice = Invoice::where('company_id', $companyId)->find($payment->invoice_id);
            }

            if ($appliesTo === 'invoice' && ! $invoice) {
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

            // 3) احسب المتاح للـ refund حسب النوع
            $refundedInvoice = (float) $payment->refunds->where('applies_to', 'invoice')->sum('amount');
            $refundedCredit  = (float) $payment->refunds->where('applies_to', 'credit')->sum('amount');

            $availableInvoice = max(0, (float)$payment->applied_amount - $refundedInvoice);
            $availableCredit  = max(0, (float)$payment->credit_amount  - $refundedCredit);

            $available = $appliesTo === 'invoice' ? $availableInvoice : $availableCredit;

            if ($amountReq > $available) {
                return response()->json([
                    'msg' => 'Refund exceeds available amount',
                    'applies_to' => $appliesTo,
                    'available' => $available,
                ], 422);
            }

            // 4) create refund
            $refund = $payment->refunds()->create([
                'company_id'  => $companyId,
                'amount'      => $amountReq,
                'applies_to'  => $appliesTo,     // ✅ مهم
                'refunded_at' => now(),
                'created_by'  => $request->user()->id ?? null
            ]);

            // 5) accounts
            $cashAccount = Account::where('company_id', $companyId)->where('code', '1000')->firstOrFail();

            $arAccount = Account::where('company_id', $companyId)->where('code', '1100')->firstOrFail();

            $creditAccount = Account::where('company_id', $companyId)->where('code', '2100')->firstOrFail();

            // 6) ledger + accounting حسب النوع
            if ($appliesTo === 'invoice') {

                // Ledger: refund على الفاتورة (debit)
                CustomerLedgerEntry::create([
                    'company_id'  => $companyId,
                    'customer_id' => $invoice->customer_id,
                    'invoice_id'  => $invoice->id,
                    'payment_id'  => $payment->id,
                    'refund_id'   => $refund->id,
                    'type'        => 'refund_invoice',
                    'debit'       => $refund->amount,
                    'credit'      => 0,
                    'entry_date'  => now()->toDateString(),
                    'description' => 'Invoice refund for payment #' . $payment->id,
                ]);

                // Accounting: Dr AR / Cr Cash
                AccountingService::createEntry(
                    $invoice,
                    'Invoice refund for payment #' . $payment->id,
                    [
                        ['account_id' => $arAccount->id, 'debit' => $refund->amount, 'credit' => 0],
                        ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $refund->amount],
                    ],
                    $request->user()->id ?? null,
                    now()->toDateString()
                );

                // Recalc invoice status: based on APPLIED - refunded(invoice)
                $totalApplied = Payment::where('company_id', $companyId)
                    ->where('invoice_id', $invoice->id)
                    ->sum('applied_amount');

                $totalRefundedInvoice = DB::table('payment_refunds')
                    ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
                    ->where('payments.company_id', $companyId)
                    ->where('payments.invoice_id', $invoice->id)
                    ->where('payment_refunds.applies_to', 'invoice')
                    ->sum('payment_refunds.amount');

                $net = (float)$totalApplied - (float)$totalRefundedInvoice;
                $remaining = max(0, (float)$invoice->total - $net);

                if ($net <= 0) $status = 'unpaid';
                elseif ($net < (float)$invoice->total) $status = 'partially_paid';
                else $status = 'paid';

                $invoice->update(['status' => $status]);

                return response()->json([
                    'msg'            => 'Refund recorded',
                    'refund_id'      => $refund->id,
                    'applies_to'     => 'invoice',
                    'invoice_status' => $status,
                    'net_paid'       => $net,
                    'remaining'      => $remaining,
                ]);
            } else {

                // Ledger: refund على credit (debit) — بدون invoice_id
                CustomerLedgerEntry::create([
                    'company_id'  => $companyId,
                    'customer_id' => $invoice?->customer_id ?? null, // لو عايز نلزمه يبقى لازم invoice
                    'invoice_id'  => null,
                    'payment_id'  => $payment->id,
                    'refund_id'   => $refund->id,
                    'type'        => 'refund_credit',
                    'debit'       => $refund->amount,
                    'credit'      => 0,
                    'entry_date'  => now()->toDateString(),
                    'description' => 'Credit refund for payment #' . $payment->id,
                ]);

                // Accounting: Dr Customer Credit / Cr Cash
                // source: invoice لو موجود وإلا payment (حسب تصميمك)
                $source = $invoice ?? $payment;

                AccountingService::createEntry(
                    $source,
                    'Credit refund for payment #' . $payment->id,
                    [
                        ['account_id' => $creditAccount->id, 'debit' => $refund->amount, 'credit' => 0],
                        ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $refund->amount],
                    ],
                    $request->user()->id ?? null,
                    now()->toDateString()
                );

                return response()->json([
                    'msg'        => 'Refund recorded',
                    'refund_id'  => $refund->id,
                    'applies_to' => 'credit',
                ]);
            }
        });
    }
}
