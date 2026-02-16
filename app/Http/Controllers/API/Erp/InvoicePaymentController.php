<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CustomerLedgerEntry;

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

        $invoice = Invoice::with([
            'payments' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            },
            'payments.refunds' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            }
        ])
            ->where('company_id', $companyId)
            ->findOrFail($invoiceId);

        // لا يمكن الدفع على فاتورة ملغاة
        if ($invoice->status === 'cancelled') {
            return response()->json([
                'msg' => 'Cannot receive payment for cancelled invoice'
            ], 422);
        }

        return DB::transaction(function () use ($invoice, $data, $request, $companyId) {

            $payment = Payment::create([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'amount'     => $data['amount'],
                'method'     => $data['method'],
                'paid_at'    => $data['paid_at'] ?? now(),
                'created_by' => $request->user()->id ?? null,
            ]);

            CustomerLedgerEntry::create([
                'company_id'  => $companyId,
                'customer_id' => $invoice->customer_id,
                'invoice_id'  => $invoice->id,
                'payment_id'  => $payment->id,
                'type'        => 'payment',
                'debit'       => 0,
                'credit'      => $payment->amount,
                'entry_date'  => now(),
                'description' => 'Payment #' . $payment->id,
            ]);

            /*
             |--------------------------------------------------------------------------
             | Accounting entry
             | Dr Cash/Bank
             | Cr Accounts Receivable
             |--------------------------------------------------------------------------
             */

            $entry = JournalEntry::create([
                'company_id'  => $companyId,
                'entry_date'  => now()->toDateString(),
                'source_type' => Payment::class,
                'source_id'   => $payment->id,
                'description' => 'Invoice payment #' . $payment->id,
                'created_by'  => $request->user()->id ?? null,
            ]);

            // لاحقاً يتم ربطها بجدول accounts حسب الشركة
            $cashAccountId = 1;
            $arAccountId   = 2;

            JournalLine::create([
                'company_id'       => $companyId,
                'journal_entry_id' => $entry->id,
                'account_id'       => $cashAccountId,
                'debit'            => $payment->amount,
                'credit'           => 0,
            ]);

            JournalLine::create([
                'company_id'       => $companyId,
                'journal_entry_id' => $entry->id,
                'account_id'       => $arAccountId,
                'debit'            => 0,
                'credit'           => $payment->amount,
            ]);

            /*
             |--------------------------------------------------------------------------
             | Recalculate invoice status
             |--------------------------------------------------------------------------
             */

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

            return response()->json([
                'msg'            => 'Payment recorded successfully',
                'payment_id'     => $payment->id,
                'invoice_status' => $status
            ], 201);
        });
    }
}
