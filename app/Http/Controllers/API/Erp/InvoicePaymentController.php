<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoicePaymentController extends Controller
{
    public function store(Request $request, $invoiceId)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $invoice = Invoice::with(['payments', 'refunds'])->findOrFail($invoiceId);

        // لا يمكن الدفع على فاتورة ملغاة
        if ($invoice->status === 'cancelled') {
            return response()->json([
                'msg' => 'Cannot receive payment for cancelled invoice'
            ], 422);
        }

        return DB::transaction(function () use ($invoice, $data, $request) {

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount'     => $data['amount'],
                'method'     => $data['method'],
                'paid_at'    => $data['paid_at'] ?? now(),
                'created_by' => $request->user()->id ?? null,
            ]);

            /*
             |--------------------------------------------------------------------------
             | Accounting entry
             | Dr Cash/Bank
             | Cr Accounts Receivable
             |--------------------------------------------------------------------------
             */

            $entry = JournalEntry::create([
                'entry_date'     => now()->toDateString(),
                'reference_type' => Payment::class,
                'reference_id'   => $payment->id,
                'description' => 'Invoice payment #' . $invoice->number,
                'created_by' => $request->user()->id ?? null,
            ]);

            // ⚠️ الأرقام التالية يجب أن تربطها لاحقاً بجدول accounts
            $cashAccountId = 1;      // Cash / Bank
            $arAccountId   = 2;      // Accounts receivable

            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $cashAccountId,
                'debit'  => $payment->amount,
                'credit' => 0,
            ]);

            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $arAccountId,
                'debit'  => 0,
                'credit' => $payment->amount,
            ]);

            /*
             |--------------------------------------------------------------------------
             | Recalculate invoice status
             |--------------------------------------------------------------------------
             */

            $paid = $invoice->payments()->sum('amount');
            $refunded = $invoice->refunds()->sum('amount');

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
                'msg' => 'Payment recorded successfully',
                'payment_id' => $payment->id,
                'invoice_status' => $status
            ], 201);
        });
    }
}
