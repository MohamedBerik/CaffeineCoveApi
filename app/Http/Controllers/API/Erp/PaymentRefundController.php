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
        $data = $request->validate([
            'amount'     => ['required', 'numeric', 'min:0.01'],
            'applies_to' => ['nullable', 'in:invoice,credit'], // ✅ جديد
        ]);

        $companyId  = $request->user()->company_id;
        $appliesTo  = $data['applies_to'] ?? 'invoice';
        $refundAmt  = (float) $data['amount'];

        return DB::transaction(function () use ($request, $paymentId, $companyId, $appliesTo, $refundAmt) {

            $payment = Payment::where('company_id', $companyId)
                ->lockForUpdate()
                ->with(['refunds']) // refunds كلها
                ->findOrFail($paymentId);

            // invoice optional: refund credit ممكن يكون بدون تأثير على invoice status
            $invoice = null;
            if ($payment->invoice_id) {
                $invoice = Invoice::where('company_id', $companyId)->find($payment->invoice_id);
            }

            // ✅ احسب إجمالي Refunds حسب applies_to
            $refundedInvoice = $payment->refunds->where('applies_to', 'invoice')->sum('amount');
            $refundedCredit  = $payment->refunds->where('applies_to', 'credit')->sum('amount');

            $appliedAmount = (float) ($payment->applied_amount ?? 0);
            $creditAmount  = (float) ($payment->credit_amount ?? 0);

            // ✅ الرصيد المتاح للـ refund حسب النوع
            $remaining = 0;
            if ($appliesTo === 'invoice') {
                if (! $invoice) {
                    return response()->json([
                        'msg' => 'Invoice not found for this payment (required for invoice refund)',
                        'debug' => [
                            'company_id' => $companyId,
                            'payment_id' => $payment->id,
                            'payment_invoice_id' => $payment->invoice_id,
                        ]
                    ], 422);
                }

                $remaining = max(0, $appliedAmount - $refundedInvoice);
            } else { // credit
                $remaining = max(0, $creditAmount - $refundedCredit);
            }

            if ($refundAmt > $remaining) {
                return response()->json([
                    'msg' => 'Refund exceeds refundable amount',
                    'applies_to' => $appliesTo,
                    'remaining'  => $remaining
                ], 422);
            }

            // ✅ Create refund row
            $refund = $payment->refunds()->create([
                'company_id'  => $companyId,
                'applies_to'  => $appliesTo,          // ✅ جديد
                'amount'      => $refundAmt,
                'refunded_at' => now(),
                'created_by'  => $request->user()->id ?? null
            ]);

            // ✅ Ledger
            if ($appliesTo === 'invoice') {
                // refund invoice => يزيد مديونية العميل على الفاتورة (debit)
                CustomerLedgerEntry::create([
                    'company_id'  => $companyId,
                    'customer_id' => $invoice->customer_id,
                    'invoice_id'  => $invoice->id,
                    'payment_id'  => $payment->id,
                    'refund_id'   => $refund->id,
                    'type'        => 'refund_invoice',
                    'debit'       => $refundAmt,
                    'credit'      => 0,
                    'entry_date'  => now()->toDateString(),
                    'description' => 'Refund (invoice) for payment #' . $payment->id,
                ]);
            } else {
                // refund credit => يقلل رصيد العميل الدائن (debit على credit balance tracking)
                // (حسب تصميم ledger عندك: debit يقلل credit balance)
                CustomerLedgerEntry::create([
                    'company_id'  => $companyId,
                    'customer_id' => $invoice?->customer_id, // لو موجودة
                    'invoice_id'  => null,
                    'payment_id'  => $payment->id,
                    'refund_id'   => $refund->id,
                    'type'        => 'refund_credit',
                    'debit'       => $refundAmt,
                    'credit'      => 0,
                    'entry_date'  => now()->toDateString(),
                    'description' => 'Refund (credit) for payment #' . $payment->id,
                ]);
            }

            // ✅ Accounts
            $cashAccount = Account::where('company_id', $companyId)->where('code', '1000')->firstOrFail();
            $arAccount   = Account::where('company_id', $companyId)->where('code', '1100')->firstOrFail();
            $creditAcc   = Account::where('company_id', $companyId)->where('code', '2100')->firstOrFail();

            // ✅ Journal Entry
            if ($appliesTo === 'invoice') {
                // Dr AR / Cr Cash
                AccountingService::createEntry(
                    $invoice,
                    'Refund (invoice) for payment #' . $payment->id,
                    [
                        ['account_id' => $arAccount->id,   'debit' => $refundAmt, 'credit' => 0],
                        ['account_id' => $cashAccount->id, 'debit' => 0,         'credit' => $refundAmt],
                    ],
                    $request->user()->id ?? null,
                    now()->toDateString()
                );
            } else {
                // Dr Customer Credit / Cr Cash
                // source الأفضل يكون invoice لو عندك، وإلا ممكن تخليه payment (لكن InvoiceJournal مش هيشوفه)
                $source = $invoice ?: $payment;

                AccountingService::createEntry(
                    $source,
                    'Refund (credit) for payment #' . $payment->id,
                    [
                        ['account_id' => $creditAcc->id,   'debit' => $refundAmt, 'credit' => 0],
                        ['account_id' => $cashAccount->id, 'debit' => 0,         'credit' => $refundAmt],
                    ],
                    $request->user()->id ?? null,
                    now()->toDateString()
                );
            }

            // ✅ Recalc invoice status ONLY if applies_to = invoice
            $status = $invoice?->status;
            $netPaid = null;

            if ($appliesTo === 'invoice' && $invoice) {

                $totalApplied = Payment::where('company_id', $companyId)
                    ->where('invoice_id', $invoice->id)
                    ->sum('applied_amount');

                $totalRefundedInvoice = DB::table('payment_refunds')
                    ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
                    ->where('payments.company_id', $companyId)
                    ->where('payments.invoice_id', $invoice->id)
                    ->where('payment_refunds.company_id', $companyId)
                    ->where('payment_refunds.applies_to', 'invoice')
                    ->sum('payment_refunds.amount');

                $netPaid = (float)$totalApplied - (float)$totalRefundedInvoice;

                if ($netPaid <= 0) $status = 'unpaid';
                elseif ($netPaid < (float)$invoice->total) $status = 'partially_paid';
                else $status = 'paid';

                $invoice->update(['status' => $status]);
            }

            activity('payment.refunded', $payment, [
                'amount'      => $refundAmt,
                'refund_id'   => $refund->id,
                'applies_to'  => $appliesTo,
                'invoice_id'  => $invoice?->id,
            ], $companyId);

            return response()->json([
                'msg'            => 'Refund recorded',
                'refund_id'      => $refund->id,
                'applies_to'     => $appliesTo,
                'invoice_status' => $status,
                'net_paid'       => $netPaid,
            ]);
        });
    }
}
