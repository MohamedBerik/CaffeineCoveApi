<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerLedgerEntry;
use App\Models\IdempotencyKey;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentRefundController extends Controller
{
    public function refund(Request $request, $paymentId)
    {
        $request->validate([
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'applies_to'  => ['nullable', 'in:invoice,credit'],
        ]);

        $companyId = $request->user()->company_id;
        $appliesTo = $request->input('applies_to', 'invoice');
        $amountReq = (float) $request->amount;

        // ✅ Idempotency-Key (header)
        $idemKey = trim((string) ($request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key') ?? ''));

        // endpoint identifier ثابت (مهم للـ unique)
        $endpoint = "POST /api/erp/payments/{$paymentId}/refund";

        // hash للـ payload عشان لو حد استخدم نفس key مع payload مختلف نرفض
        $payloadForHash = [
            'payment_id'  => (int) $paymentId,
            'amount'      => (string) number_format($amountReq, 2, '.', ''),
            'applies_to'  => $appliesTo,
        ];
        $requestHash = hash('sha256', json_encode($payloadForHash));

        return DB::transaction(function () use ($request, $paymentId, $companyId, $appliesTo, $amountReq, $idemKey, $endpoint, $requestHash) {

            $idemRow = null;

            // 1) ✅ لو فيه Idempotency-Key: حاول "تحجز" المفتاح
            if ($idemKey !== '') {

                // لو موجود قبل كده: رجّع نفس الرد القديم
                $existing = IdempotencyKey::where('company_id', $companyId)
                    ->where('key', $idemKey)
                    ->where('endpoint', $endpoint)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    // لو نفس الـ key اتستخدم مع payload مختلف => 409 (Misuse)
                    if ($existing->request_hash !== $requestHash) {
                        return response()->json([
                            'msg' => 'Idempotency-Key conflict: same key used with different request body',
                        ], 409);
                    }

                    // لو الرد متخزن: رجّعه كما هو (بدون تنفيذ refund)
                    if (!is_null($existing->status_code) && !is_null($existing->response_body)) {
                        return response()->json($existing->response_body, (int) $existing->status_code);
                    }

                    // لو موجود بس لسه مفيش response (طلب سابق لسه بيشتغل) => 409
                    return response()->json([
                        'msg' => 'Request is already being processed',
                    ], 409);
                }

                // مش موجود => اعمل row جديد "in-progress"
                $idemRow = IdempotencyKey::create([
                    'company_id'    => $companyId,
                    'key'           => $idemKey,
                    'endpoint'      => $endpoint,
                    'request_hash'  => $requestHash,
                    'status_code'   => null,
                    'response_body' => null,
                ]);
            }

            // 2) lock payment + load refunds
            $payment = Payment::where('company_id', $companyId)
                ->lockForUpdate()
                ->with(['refunds'])
                ->findOrFail($paymentId);

            // 3) invoice required (حتى للـ credit refund عشان نعرف customer_id)
            $invoice = $payment->invoice_id
                ? Invoice::where('company_id', $companyId)->find($payment->invoice_id)
                : null;

            if (!$invoice) {
                $resp = [
                    'msg' => 'Invoice not found for this payment',
                    'debug' => [
                        'request_company_id' => $companyId,
                        'payment_id' => $payment->id,
                        'payment_company_id' => $payment->company_id,
                        'payment_invoice_id' => $payment->invoice_id,
                    ]
                ];
                // خزّن الرد لو idem
                if ($idemRow) {
                    $idemRow->update(['status_code' => 422, 'response_body' => $resp]);
                }
                return response()->json($resp, 422);
            }

            // 4) available refund by type
            $refundedInvoice = (float) $payment->refunds->where('applies_to', 'invoice')->sum('amount');
            $refundedCredit  = (float) $payment->refunds->where('applies_to', 'credit')->sum('amount');

            $availableInvoice = max(0, (float) $payment->applied_amount - $refundedInvoice);
            $availableCredit  = max(0, (float) $payment->credit_amount  - $refundedCredit);

            $available = $appliesTo === 'invoice' ? $availableInvoice : $availableCredit;

            if ($amountReq > $available) {
                $resp = [
                    'msg' => 'Refund exceeds available amount',
                    'applies_to' => $appliesTo,
                    'available' => $available,
                ];
                if ($idemRow) {
                    $idemRow->update(['status_code' => 422, 'response_body' => $resp]);
                }
                return response()->json($resp, 422);
            }

            // 5) create refund
            $refund = $payment->refunds()->create([
                'company_id'  => $companyId,
                'amount'      => $amountReq,
                'applies_to'  => $appliesTo,
                'refunded_at' => now(),
                'created_by'  => $request->user()->id ?? null,
            ]);

            // 6) accounts
            $cashAccount   = Account::where('company_id', $companyId)->where('code', '1000')->firstOrFail();
            $arAccount     = Account::where('company_id', $companyId)->where('code', '1100')->firstOrFail();
            $creditAccount = Account::where('company_id', $companyId)->where('code', '2100')->firstOrFail();

            // 7) ledger + accounting
            if ($appliesTo === 'invoice') {

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

                AccountingService::createEntry(
                    $invoice,
                    'Invoice refund for payment #' . $payment->id,
                    [
                        ['account_id' => $arAccount->id,   'debit' => $refund->amount, 'credit' => 0],
                        ['account_id' => $cashAccount->id, 'debit' => 0,               'credit' => $refund->amount],
                    ],
                    $request->user()->id ?? null,
                    now()->toDateString()
                );

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

                $net = (float) $totalApplied - (float) $totalRefundedInvoice;
                $remaining = max(0, (float) $invoice->total - $net);

                if ($net <= 0) $status = 'unpaid';
                elseif ($net < (float)$invoice->total) $status = 'partially_paid';
                else $status = 'paid';

                $invoice->update(['status' => $status]);

                $resp = [
                    'msg'            => 'Refund recorded',
                    'refund_id'      => $refund->id,
                    'applies_to'     => 'invoice',
                    'invoice_status' => $status,
                    'net_paid'       => $net,
                    'remaining'      => $remaining,
                ];

                if ($idemRow) {
                    $idemRow->update(['status_code' => 200, 'response_body' => $resp]);
                }

                return response()->json($resp);
            } else {

                // ✅ CREDIT REFUND: لازم invoice_id = null في ledger
                CustomerLedgerEntry::create([
                    'company_id'  => $companyId,
                    'customer_id' => $invoice->customer_id,
                    'invoice_id'  => null,
                    'payment_id'  => $payment->id,
                    'refund_id'   => $refund->id,
                    'type'        => 'refund_credit',
                    'debit'       => $refund->amount,
                    'credit'      => 0,
                    'entry_date'  => now()->toDateString(),
                    'description' => 'Credit refund for payment #' . $payment->id,
                ]);

                // ✅ customer_credits: unique on (company_id, refund_id) عندك بالفعل
                DB::table('customer_credits')->insert([
                    'company_id'  => $companyId,
                    'customer_id' => $invoice->customer_id,
                    'invoice_id'  => null,
                    'payment_id'  => $payment->id,
                    'refund_id'   => $refund->id,
                    'type'        => 'debit',
                    'amount'      => $refund->amount,
                    'entry_date'  => now()->toDateString(),
                    'description' => 'Credit refunded from payment #' . $payment->id . ' (refund #' . $refund->id . ')',
                    'created_by'  => $request->user()->id ?? null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                AccountingService::createEntry(
                    $invoice,
                    'Credit refund for payment #' . $payment->id,
                    [
                        ['account_id' => $creditAccount->id, 'debit' => $refund->amount, 'credit' => 0],
                        ['account_id' => $cashAccount->id,   'debit' => 0,               'credit' => $refund->amount],
                    ],
                    $request->user()->id ?? null,
                    now()->toDateString()
                );

                $resp = [
                    'msg'        => 'Refund recorded',
                    'refund_id'  => $refund->id,
                    'applies_to' => 'credit',
                ];

                if ($idemRow) {
                    $idemRow->update(['status_code' => 200, 'response_body' => $resp]);
                }

                return response()->json($resp);
            }
        });
    }
}
