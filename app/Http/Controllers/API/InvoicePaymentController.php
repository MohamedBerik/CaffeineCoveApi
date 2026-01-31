<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoicePaymentController extends Controller
{
    public function pay(Request $request, $id)
    {
        $invoice = Invoice::with('payments')->findOrFail($id);

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string']
        ]);

        if ($invoice->status === 'paid') {
            return response()->json([
                'msg' => 'Invoice already paid'
            ], 422);
        }

        $alreadyPaid = $invoice->payments->sum('amount');
        $remaining   = $invoice->total - $alreadyPaid;

        if ($request->amount > $remaining) {
            return response()->json([
                'msg' => 'Payment exceeds remaining amount',
                'remaining' => $remaining
            ], 422);
        }

        return DB::transaction(function () use ($invoice, $request, $remaining) {

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount'     => $request->amount,
                'method'     => $request->method,
                'paid_at'    => now(),
                'received_by' => $request->user()->id ?? null
            ]);

            $newPaid = $invoice->payments()->sum('amount') + $request->amount;

            if ($newPaid >= $invoice->total) {

                $invoice->update([
                    'status' => 'paid'
                ]);
            } else {

                $invoice->update([
                    'status' => 'partial'
                ]);
            }

            return response()->json([
                'msg' => 'Payment recorded',
                'invoice_status' => $invoice->fresh()->status,
                'payment_id' => $payment->id
            ]);
        });
    }
}
