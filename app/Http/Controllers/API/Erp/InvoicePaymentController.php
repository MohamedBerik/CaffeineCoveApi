<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerLedgerEntry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoicePaymentController extends Controller
{
    public function store(Request $request, $invoiceId)
    {
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'method'            => ['required', 'string'],
            'paid_at'           => ['nullable', 'date'],
            'allow_overpayment' => ['nullable', 'boolean'],
        ]);

        $allowOverpayment = (bool)($data['allow_overpayment'] ?? false);

        return DB::transaction(function () use ($request, $invoiceId, $companyId, $data, $allowOverpayment) {

            $invoice = Invoice::where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            if ($invoice->status === 'cancelled') {
                return response()->json(['msg' => 'Cannot receive payment for cancelled invoice'], 422);
            }

            // remaining based on applied/refunded(invoice only)
            $totalApplied = Payment::where('company_id', $companyId)->where('invoice_id', $invoice->id)->sum('applied_amount');

            $totalRefundedInvoice = PaymentRefund::where('company_id', $companyId)
                ->where('applies_to', 'invoice')
                ->whereHas('payment', function ($q) use ($companyId, $invoice) {
                    $q->where('company_id', $companyId)->where('invoice_id', $invoice->id);
                })
                ->sum('amount');

            $netPaid = $totalApplied - $totalRefundedInvoice;
            $remaining = max(0, (float)$invoice->total - (float)$netPaid);

            if (!$allowOverpayment && (float)$data['amount'] > $remaining) {
                return response()->json([
                    'msg' => 'Payment exceeds remaining amount',
                    'remaining' => $remaining
                ], 422);
            }

            if (!$allowOverpayment && $remaining <= 0) {
                return response()->json([
                    'msg' => 'Invoice is already fully paid',
                    'remaining' => 0
                ], 422);
            }

            $amount = (float)$data['amount'];
            $applied = min($amount, $remaining);
            $credit  = max(0, $amount - $applied);

            $payment = Payment::create([
                'company_id'     => $companyId,
                'invoice_id'     => $invoice->id,
                'amount'         => $amount,
                'applied_amount' => $applied,
                'credit_amount'  => $credit,
                'method'         => $data['method'],
                'paid_at'        => $data['paid_at'] ?? now(),
                'received_by'    => $request->user()->id ?? null,
            ]);

            // Ledger: payment applied to invoice
            if ($applied > 0) {
                CustomerLedgerEntry::create([
                    'company_id'  => $companyId,
                    'customer_id' => $invoice->customer_id,
                    'invoice_id'  => $invoice->id,
                    'payment_id'  => $payment->id,
                    'refund_id'   => null,
                    'type'        => 'payment',
                    'debit'       => 0,
                    'credit'      => $applied,
                    'entry_date'  => now()->toDateString(),
                    'description' => 'Payment applied #' . $payment->id,
                ]);
            }

            // Ledger: credit issued from overpayment
            if ($credit > 0) {
                CustomerLedgerEntry::create([
                    'company_id'  => $companyId,
                    'customer_id' => $invoice->customer_id,
                    'invoice_id'  => null,
                    'payment_id'  => $payment->id,
                    'refund_id'   => null,
                    'type'        => 'credit_issued',
                    'debit'       => 0,
                    'credit'      => $credit,
                    'entry_date'  => now()->toDateString(),
                    'description' => 'Customer credit issued from payment #' . $payment->id,
                ]);
            }

            // Accounts
            $cashAccount = Account::where('company_id', $companyId)->where('code', '1000')->firstOrFail();
            $arAccount   = Account::where('company_id', $companyId)->where('code', '1100')->firstOrFail();
            $creditAccount = Account::where('company_id', $companyId)->where('code', '2100')->firstOrFail();

            // Accounting: split
            $lines = [];

            // Dr Cash full amount
            $lines[] = ['account_id' => $cashAccount->id, 'debit' => $amount, 'credit' => 0];

            // Cr AR applied
            if ($applied > 0) {
                $lines[] = ['account_id' => $arAccount->id, 'debit' => 0, 'credit' => $applied];
            }

            // Cr Customer Credit for extra
            if ($credit > 0) {
                $lines[] = ['account_id' => $creditAccount->id, 'debit' => 0, 'credit' => $credit];
            }

            AccountingService::createEntry(
                $invoice,
                'Invoice payment #' . $payment->id,
                $lines,
                $request->user()->id ?? null,
                now()->toDateString()
            );

            // Update invoice status using applied only
            $netAfter = ($netPaid + $applied);
            $remainingAfter = max(0, (float)$invoice->total - (float)$netAfter);

            if ($netAfter <= 0) $status = 'unpaid';
            elseif ($netAfter < (float)$invoice->total) $status = 'partially_paid';
            else $status = 'paid';

            $invoice->update(['status' => $status]);

            return response()->json([
                'msg'            => 'Payment recorded successfully',
                'payment_id'     => $payment->id,
                'invoice_status' => $status,
                'applied'        => $applied,
                'credit_issued'  => $credit,
                'net_paid'       => $netAfter,
                'remaining'      => $remainingAfter,
            ], 201);
        });
    }
}
