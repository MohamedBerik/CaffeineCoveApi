<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Payment;
use App\Services\AccountingService;
use App\Models\CustomerLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentRefundController extends Controller
{
    public function refund(Request $request, $paymentId)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $companyId = $request->user()->company_id;
        $amount    = (float) $data['amount'];

        return DB::transaction(function () use ($request, $paymentId, $companyId, $amount) {

            // ✅ اقفل الـ payment لمنع تنفيذ refund مرتين في نفس اللحظة
            $payment = Payment::where('company_id', $companyId)
                ->lockForUpdate()
                ->with([
                    'invoice' => fn($q) => $q->where('company_id', $companyId),
                    'refunds' => fn($q) => $q->where('company_id', $companyId),
                ])
                ->findOrFail($paymentId);

            // ✅ تأكد أن payment مرتبط بفاتورة (احتياطي)
            if (! $payment->invoice) {
                return response()->json([
                    'msg' => 'Invoice not found for this payment'
                ], 422);
            }

            $alreadyRefunded = (float) $payment->refunds->sum('amount');
            $remaining       = (float) $payment->amount - $alreadyRefunded;

            if ($remaining <= 0) {
                return response()->json([
                    'msg' => 'No refundable amount remaining for this payment',
                ], 422);
            }

            if ($amount > $remaining) {
                return response()->json([
                    'msg'       => 'Refund exceeds paid amount',
                    'remaining' => $remaining
                ], 422);
            }

            // ✅ إنشاء refund (company_id يفضل كتابته صراحة هنا - واضح + آمن)
            $refund = $payment->refunds()->create([
                'company_id'  => $companyId,
                'amount'      => $amount,
                'refunded_at' => now(),
                'created_by'  => $request->user()->id ?? null,
            ]);

            // ✅ قيد كشف حساب العميل (refund = debit)
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

            // ✅ الحسابات الصحيحة داخل نفس الشركة
            $cashAccount = Account::where('company_id', $companyId)
                ->where('code', '1000') // Cash/Bank
                ->firstOrFail();

            $arAccount = Account::where('company_id', $companyId)
                ->where('code', '1100') // Accounts Receivable
                ->firstOrFail();

            /*
             |--------------------------------------------------------------------------
             | Accounting entry (Refund)
             | Dr Accounts Receivable (1100)  => يزيد مديونية العميل
             | Cr Cash/Bank (1000)            => يقل النقدية
             |--------------------------------------------------------------------------
             |
             | ✅ ملاحظة مهمة:
             | AccountingService بعد تعديلك أصبح يكتب company_id على entry و lines تلقائيًا
             | لذلك لا نعمل أي ترقيع هنا.
             */
            AccountingService::createEntry(
                $payment->invoice, // source = invoice (عشان يظهر في InvoiceJournal)
                'Refund for payment #' . $payment->id,
                [
                    [
                        'account_id' => $arAccount->id,
                        'debit'      => $amount,
                        'credit'     => 0
                    ],
                    [
                        'account_id' => $cashAccount->id,
                        'debit'      => 0,
                        'credit'     => $amount
                    ]
                ],
                $request->user()->id ?? null,
                now()->toDateString()
            );

            /*
             |--------------------------------------------------------------------------
             | Recalculate invoice status (بدقة من DB)
             |--------------------------------------------------------------------------
             */
            $invoice = $payment->invoice->fresh();

            $paid = DB::table('payments')
                ->where('company_id', $companyId)
                ->where('invoice_id', $invoice->id)
                ->sum('amount');

            $refunded = DB::table('payment_refunds')
                ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
                ->where('payments.company_id', $companyId)
                ->where('payments.invoice_id', $invoice->id)
                ->sum('payment_refunds.amount');

            $net = (float) $paid - (float) $refunded;

            if ($net <= 0) {
                $status = 'unpaid';
            } elseif ($net < (float) $invoice->total) {
                $status = 'partially_paid';
            } else {
                $status = 'paid';
            }

            $invoice->update(['status' => $status]);

            activity('payment.refunded', $payment, [
                'amount'    => $amount,
                'refund_id' => $refund->id,
            ], $companyId);

            return response()->json([
                'msg'            => 'Refund recorded',
                'refund_id'      => $refund->id,
                'invoice_id'     => $invoice->id,
                'invoice_status' => $status,
                'net_paid'       => $net,
            ]);
        });
    }
}
