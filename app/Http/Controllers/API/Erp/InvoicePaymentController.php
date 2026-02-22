<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerLedgerEntry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoicePaymentController extends Controller
{
    public function store(Request $request, $invoiceId)
    {
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'amount'  => ['required', 'numeric', 'min:0.01'],
            'method'  => ['required', 'string'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $amount = (float) $data['amount'];

        return DB::transaction(function () use ($request, $invoiceId, $companyId, $data, $amount) {

            // ✅ Lock invoice to prevent race conditions
            $invoice = Invoice::where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            if ($invoice->status === 'cancelled') {
                return response()->json([
                    'msg' => 'Cannot receive payment for cancelled invoice'
                ], 422);
            }

            // ✅ Calculate totals from DB (safe + no pluck)
            $totalPaid = (float) Payment::where('company_id', $companyId)
                ->where('invoice_id', $invoice->id)
                ->sum('amount');

            $totalRefunded = (float) DB::table('payment_refunds')
                ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
                ->where('payments.company_id', $companyId)
                ->where('payments.invoice_id', $invoice->id)
                ->sum('payment_refunds.amount');

            $netPaid   = $totalPaid - $totalRefunded;
            $remaining = max(0, (float) $invoice->total - $netPaid);

            if ($remaining <= 0) {
                return response()->json([
                    'msg' => 'Invoice is already fully paid',
                    'remaining' => 0
                ], 422);
            }

            // ✅ Prevent overpayment
            if ($amount > $remaining) {
                return response()->json([
                    'msg' => 'Payment exceeds remaining amount',
                    'remaining' => $remaining
                ], 422);
            }

            // ✅ Create payment (received_by is correct per your schema)
            $payment = Payment::create([
                'company_id'  => $companyId,
                'invoice_id'  => $invoice->id,
                'amount'      => $amount,
                'method'      => $data['method'],
                'paid_at'     => $data['paid_at'] ?? now(),
                'received_by' => $request->user()->id ?? null,
            ]);

            // ✅ Customer ledger entry (credit)
            CustomerLedgerEntry::create([
                'company_id'  => $companyId,
                'customer_id' => $invoice->customer_id,
                'invoice_id'  => $invoice->id,
                'payment_id'  => $payment->id,
                'refund_id'   => null,
                'type'        => 'payment',
                'debit'       => 0,
                'credit'      => $payment->amount,
                'entry_date'  => now()->toDateString(),
                'description' => 'Payment #' . $payment->id,
            ]);

            /*
             |--------------------------------------------------------------------------
             | Accounting Entry (multi-tenant safe)
             | Dr Cash/Bank (1000)
             | Cr Accounts Receivable (1100)
             |--------------------------------------------------------------------------
             */
            $cashAccount = Account::where('company_id', $companyId)
                ->where('code', '1000')
                ->firstOrFail();

            $arAccount = Account::where('company_id', $companyId)
                ->where('code', '1100')
                ->firstOrFail();

            AccountingService::createEntry(
                $invoice, // ✅ source = invoice (عشان InvoiceJournal يعرض كل القيود الخاصة بالفاتورة)
                'Invoice payment #' . $payment->id,
                [
                    [
                        'account_id' => $cashAccount->id,
                        'debit'      => $payment->amount,
                        'credit'     => 0,
                    ],
                    [
                        'account_id' => $arAccount->id,
                        'debit'      => 0,
                        'credit'     => $payment->amount,
                    ],
                ],
                $request->user()->id ?? null,
                now()->toDateString()
            );

            // ✅ Recalculate AFTER inserting payment (بدون re-query)
            $totalPaidAfter = $totalPaid + (float) $payment->amount;
            $netAfter       = $totalPaidAfter - $totalRefunded;
            $remainingAfter = max(0, (float) $invoice->total - $netAfter);

            if ($netAfter <= 0) {
                $status = 'unpaid';
            } elseif ($netAfter < (float) $invoice->total) {
                $status = 'partially_paid';
            } else {
                $status = 'paid';
            }

            $invoice->update(['status' => $status]);

            activity('invoice.paid', $invoice, [
                'amount'     => $payment->amount,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
            ], $companyId);

            return response()->json([
                'msg'            => 'Payment recorded successfully',
                'payment_id'     => $payment->id,
                'invoice_status' => $status,
                'net_paid'       => $netAfter,
                'remaining'      => $remainingAfter,
            ], 201);
        });
    }
}
