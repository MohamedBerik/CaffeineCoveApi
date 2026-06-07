<?php

namespace App\Services\Erp;

use App\Events\DashboardUpdated;
use App\Events\InsightGenerated;
use App\Models\Account;
use App\Models\CustomerLedgerEntry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AccountingService;
use App\Services\InsightService;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoicePaymentService
{
    public function store(Request $request, $invoiceId)
    {
        $companyId = Tenant::id();
        $branchId  = Tenant::branchId() ?? $request->user()->branch_id;

        $data = $request->validate([
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'method'            => ['required', 'string'],
            'paid_at'           => ['nullable', 'date'],
            'allow_overpayment' => ['nullable', 'boolean'],
        ]);

        $allowOverpayment = (bool)($data['allow_overpayment'] ?? false);

        return DB::transaction(function () use ($request, $invoiceId, $companyId, $data, $allowOverpayment, $branchId) {

            $invoice = Invoice::lockForUpdate()->findOrFail($invoiceId);

            if ($invoice->status === 'cancelled') {
                return response()->json(['msg' => 'Cannot receive payment for cancelled invoice'], 422);
            }

            // 1) total applied
            $totalApplied = Payment::where('invoice_id', $invoice->id)
                ->sum('applied_amount');

            // 2) total refunded for invoice portion only
            $totalRefunded = DB::table('payment_refunds')
                ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
                ->where('payments.invoice_id', $invoice->id)
                ->where('payment_refunds.applies_to', 'invoice')
                ->sum('payment_refunds.amount');

            // 3) total credit applied to this invoice
            $totalCreditApplied = DB::table('customer_credits')
                ->where('invoice_id', $invoice->id)
                ->where('type', 'debit')
                ->sum('amount');

            $netPaid   = (float)$totalApplied - (float)$totalRefunded + (float)$totalCreditApplied;
            $remaining = max(0, (float)$invoice->total - (float)$netPaid);

            if (!$allowOverpayment && $remaining <= 0) {
                return response()->json([
                    'msg' => 'Invoice is already fully paid',
                    'remaining' => 0
                ], 422);
            }

            if (!$allowOverpayment && (float)$data['amount'] > $remaining) {
                return response()->json([
                    'msg' => 'Payment exceeds remaining amount',
                    'remaining' => $remaining
                ], 422);
            }

            $amount  = (float)$data['amount'];
            $applied = min($amount, $remaining);
            $credit  = max(0, $amount - $applied);

            $payment = Payment::create([
                'company_id'     => $companyId,
                'branch_id'      => Tenant::branchId() ?? $request->user()->branch_id,
                'invoice_id'     => $invoice->id,
                'amount'         => $amount,
                'applied_amount' => $applied,
                'credit_amount'  => $credit,
                'method'         => $data['method'],
                'paid_at'        => $data['paid_at'] ?? now(),
                'received_by'    => $request->user()->id ?? null,
            ]);

            $payment->forceFill([
                'applied_amount' => $applied,
                'credit_amount'  => $credit,
            ])->save();

            // Customer Ledger (AR ledger)
            if ($applied > 0) {
                CustomerLedgerEntry::create([
                    'company_id'  => $companyId,
                    'branch_id'   => $branchId,   // ✅ أضف هذا السطر
                    'customer_id' => $invoice->customer_id,
                    'invoice_id'  => $invoice->id,
                    'payment_id'  => $payment->id,
                    'refund_id'   => null,
                    'type'        => 'payment',
                    'debit'       => 0,
                    'credit'      => $applied,
                    'entry_date'  => $payment->created_at,
                    'description' => 'Payment applied #' . $payment->id,
                ]);
            }

            // Customer Credit Ledger (overpayment)
            if ($credit > 0) {
                DB::table('customer_credits')->insert([
                    'company_id'   => $companyId,
                    'customer_id'  => $invoice->customer_id,
                    'invoice_id'   => null,
                    'payment_id'   => $payment->id,
                    'type'         => 'credit',
                    'amount'       => $credit,
                    'entry_date'   => $payment->created_at,
                    'description'  => 'Overpayment credit from payment #' . $payment->id,
                    'created_by'   => $request->user()->id ?? null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            // Accounts
            $cashAccount   = Account::where('code', '1000')->first();
            $arAccount     = Account::where('code', '1100')->first();
            $creditAccount = Account::where('code', '2100')->first();

            if (!$cashAccount || !$arAccount || !$creditAccount) {
                return response()->json([
                    'msg' => 'Required accounting accounts are missing for this company.',
                    'missing_accounts' => [
                        'cash_1000'            => !$cashAccount,
                        'ar_1100'              => !$arAccount,
                        'customer_credit_2100' => !$creditAccount,
                    ],
                ], 422);
            }

            // Accounting entry
            $lines = [
                ['account_id' => $cashAccount->id, 'debit' => $amount, 'credit' => 0],
            ];
            if ($applied > 0) {
                $lines[] = ['account_id' => $arAccount->id, 'debit' => 0, 'credit' => $applied];
            }
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

            $netAfter       = $netPaid + $applied;
            $remainingAfter = max(0, (float)$invoice->total - (float)$netAfter);

            if ($netAfter <= 0) {
                $status = 'unpaid';
            } elseif ($netAfter < (float)$invoice->total) {
                $status = 'partially_paid';
            } else {
                $status = 'paid';
            }

            $invoice->update(['status' => $status]);

            event(new DashboardUpdated(
                $companyId,
                'payment_created',
                [
                    'branch_id' => $branchId,
                    'today_revenue' => $applied,
                    'month_revenue' => $applied,
                ]
            ));

            if ($status === 'paid') {
                event(new DashboardUpdated(
                    $companyId,
                    'invoice_paid',
                    [
                        'branch_id' => $branchId,
                        'invoice_id' => $invoice->id,
                    ]
                ));
            }

            $insight = app(InsightService::class)->revenueInsight($companyId);
            if ($insight) {
                event(new InsightGenerated(
                    $companyId,
                    $branchId,
                    $insight
                ));
            }

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
