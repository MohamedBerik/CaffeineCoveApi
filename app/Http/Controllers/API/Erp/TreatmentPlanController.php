<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\TreatmentPlan;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TreatmentPlanController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $plans = TreatmentPlan::query()
            ->where('company_id', $companyId)
            ->with(['customer:id,name,email,company_id'])
            ->orderByDesc('id')
            ->paginate(20);

        // enrich with computed totals (paid/refunded/net/remaining) from invoices
        $plans->getCollection()->transform(function ($plan) use ($companyId) {
            return $this->planResponse($plan, $companyId);
        });

        return response()->json($plans);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $plan = TreatmentPlan::where('company_id', $companyId)
            ->with([
                'customer:id,name,email,company_id',
                'invoices' => function ($q) use ($companyId) {
                    $q->where('company_id', $companyId)
                        ->with(['items.product', 'payments.refunds', 'journalEntries.lines.account'])
                        ->orderByDesc('id');
                }
            ])
            ->findOrFail($id);

        return response()->json($this->planResponse($plan, $companyId, true));
    }

    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'title'       => ['required', 'string', 'max:190'],
            'notes'       => ['nullable', 'string'],
            'total_cost'  => ['required', 'numeric', 'min:0'],
        ]);

        $plan = TreatmentPlan::create([
            'company_id'  => $companyId,
            'customer_id' => $data['customer_id'],
            'title'       => $data['title'],
            'notes'       => $data['notes'] ?? null,
            'total_cost'  => $data['total_cost'],
            'status'      => 'active',
        ]);

        return response()->json([
            'msg' => 'Treatment plan created',
            'data' => $this->planResponse($plan->load('customer'), $companyId),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $plan = TreatmentPlan::where('company_id', $companyId)->findOrFail($id);

        $data = $request->validate([
            'title'      => ['nullable', 'string', 'max:190'],
            'notes'      => ['nullable', 'string'],
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'status'     => ['nullable', 'in:active,completed,cancelled'],
        ]);

        $plan->update($data);

        return response()->json([
            'msg' => 'Treatment plan updated',
            'data' => $this->planResponse($plan->fresh()->load('customer'), $companyId),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $plan = TreatmentPlan::where('company_id', $companyId)->findOrFail($id);

        // safety: prevent delete if linked invoices exist
        $hasInvoices = Invoice::where('company_id', $companyId)
            ->where('treatment_plan_id', $plan->id)
            ->exists();

        if ($hasInvoices) {
            return response()->json([
                'msg' => 'Cannot delete plan with linked invoices',
            ], 422);
        }

        $plan->delete();

        return response()->json(['msg' => 'Treatment plan deleted']);
    }

    /**
     * ✅ Invoice-based: totals for plan based on invoices payments (applied_amount)
     * ✅ refunds counted ONLY when applies_to=invoice
     * ❌ refunds applies_to=credit NOT included here
     */
    private function planResponse(TreatmentPlan $plan, int $companyId, bool $withInvoices = false): array
    {
        $invoiceIds = $withInvoices && $plan->relationLoaded('invoices')
            ? $plan->invoices->pluck('id')->all()
            : Invoice::where('company_id', $companyId)
            ->where('treatment_plan_id', $plan->id)
            ->pluck('id')
            ->all();

        $totalApplied = 0.0;
        $totalRefundedInvoice = 0.0;

        if (!empty($invoiceIds)) {
            $totalApplied = (float) DB::table('payments')
                ->where('company_id', $companyId)
                ->whereIn('invoice_id', $invoiceIds)
                ->sum('applied_amount');

            $totalRefundedInvoice = (float) DB::table('payment_refunds')
                ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
                ->where('payments.company_id', $companyId)
                ->whereIn('payments.invoice_id', $invoiceIds)
                ->where('payment_refunds.company_id', $companyId)
                ->where('payment_refunds.applies_to', 'invoice')
                ->sum('payment_refunds.amount');
        }

        $netPaid = $totalApplied - $totalRefundedInvoice;
        $remaining = max(0, (float) $plan->total_cost - $netPaid);

        $resp = [
            'id'         => $plan->id,
            'customer_id' => $plan->customer_id,
            'title'      => $plan->title,
            'notes'      => $plan->notes,
            'total_cost' => (float) $plan->total_cost,
            'status'     => $plan->status,
            'created_at' => $plan->created_at,
            'updated_at' => $plan->updated_at,

            'customer'   => $plan->relationLoaded('customer') ? $plan->customer : null,

            // computed (invoice-based)
            'total_paid'     => (float) $totalApplied,
            'total_refunded' => (float) $totalRefundedInvoice,
            'net_paid'       => (float) $netPaid,
            'remaining'      => (float) $remaining,
        ];

        if ($withInvoices) {
            $resp['invoices'] = $plan->relationLoaded('invoices') ? $plan->invoices : [];
        }

        return $resp;
    }

    /**
     * ✅ Plan Summary (Invoice-based)
     * - paid = payments.applied_amount
     * - refunded = refunds where applies_to=invoice
     */
    public function summary(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $plan = TreatmentPlan::where('company_id', $companyId)->findOrFail($id);

        $invoices = Invoice::query()
            ->where('company_id', $companyId)
            ->where('treatment_plan_id', $plan->id)
            ->orderBy('issued_at', 'asc')
            ->orderBy('id', 'asc')
            ->get([
                'id',
                'number',
                'customer_id',
                'appointment_id',
                'total',
                'status',
                'issued_at',
                'created_at',
                'updated_at',
            ]);

        $invoiceIds = $invoices->pluck('id')->values();

        if ($invoiceIds->isEmpty()) {
            return response()->json([
                'msg' => 'Treatment plan summary',
                'status' => 200,
                'data' => [
                    'plan' => [
                        'id' => $plan->id,
                        'customer_id' => $plan->customer_id,
                        'title' => $plan->title,
                        'notes' => $plan->notes,
                        'total_cost' => (float) $plan->total_cost,
                        'status' => $plan->status,
                        'created_at' => $plan->created_at,
                        'updated_at' => $plan->updated_at,
                    ],
                    'totals' => [
                        'total_invoiced' => 0.0,
                        'total_paid' => 0.0,
                        'total_refunded' => 0.0,
                        'net_paid' => 0.0,
                        'remaining_on_plan' => (float) $plan->total_cost,
                    ],
                    'invoices' => [],
                ],
            ]);
        }

        $paidByInvoice = DB::table('payments')
            ->where('company_id', $companyId)
            ->whereIn('invoice_id', $invoiceIds)
            ->select('invoice_id', DB::raw('SUM(applied_amount) as total_paid'))
            ->groupBy('invoice_id')
            ->pluck('total_paid', 'invoice_id');

        $refundedByInvoice = DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->where('payments.company_id', $companyId)
            ->where('payment_refunds.company_id', $companyId)
            ->whereIn('payments.invoice_id', $invoiceIds)
            ->where('payment_refunds.applies_to', 'invoice')
            ->select('payments.invoice_id as invoice_id', DB::raw('SUM(payment_refunds.amount) as total_refunded'))
            ->groupBy('payments.invoice_id')
            ->pluck('total_refunded', 'invoice_id');

        $invoiceRows = $invoices->map(function ($inv) use ($paidByInvoice, $refundedByInvoice) {
            $paid = (float) ($paidByInvoice[$inv->id] ?? 0);
            $ref  = (float) ($refundedByInvoice[$inv->id] ?? 0);
            $net  = $paid - $ref;

            return [
                'id' => $inv->id,
                'number' => $inv->number,
                'customer_id' => $inv->customer_id,
                'appointment_id' => $inv->appointment_id,
                'total' => (float) $inv->total,
                'status' => $inv->status,
                'issued_at' => $inv->issued_at,
                'total_paid' => $paid,
                'total_refunded' => $ref,
                'net_paid' => $net,
                'remaining' => max(0, (float) $inv->total - $net),
            ];
        });

        $totalInvoiced = (float) $invoices->sum(fn($i) => (float) $i->total);
        $totalPaid     = (float) $invoiceRows->sum('total_paid');
        $totalRefunded = (float) $invoiceRows->sum('total_refunded');
        $netPaid       = $totalPaid - $totalRefunded;
        $remainingOnPlan = max(0, (float) $plan->total_cost - $netPaid);

        return response()->json([
            'msg' => 'Treatment plan summary',
            'status' => 200,
            'data' => [
                'plan' => [
                    'id' => $plan->id,
                    'customer_id' => $plan->customer_id,
                    'title' => $plan->title,
                    'notes' => $plan->notes,
                    'total_cost' => (float) $plan->total_cost,
                    'status' => $plan->status,
                    'created_at' => $plan->created_at,
                    'updated_at' => $plan->updated_at,
                ],
                'totals' => [
                    'total_invoiced' => $totalInvoiced,
                    'total_paid' => $totalPaid,
                    'total_refunded' => $totalRefunded,
                    'net_paid' => $netPaid,
                    'remaining_on_plan' => $remainingOnPlan,
                ],
                'invoices' => $invoiceRows->values(),
            ],
        ]);
    }

    /**
     * ✅ NEW: Cash summary for a treatment plan (cash-in / cash-out)
     *
     * - cash_in: SUM(payments.amount) for payments on invoices of this plan
     * - cash_out_invoice_refunds: refunds applies_to=invoice for those payments
     * - cash_out_credit_refunds: refunds applies_to=credit for those payments
     * - net_cash = cash_in - (cash_out_invoice_refunds + cash_out_credit_refunds)
     *
     * Plus customer wallet balance from customer_credits:
     * - credit_issued (type=credit)
     * - credit_used   (type=debit)
     * - net_credit
     */
    public function cashSummary(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $plan = TreatmentPlan::where('company_id', $companyId)->findOrFail($id);

        $invoiceIds = Invoice::where('company_id', $companyId)
            ->where('treatment_plan_id', $plan->id)
            ->pluck('id');

        // No invoices => still return wallet
        if ($invoiceIds->isEmpty()) {
            $creditIssued = (float) DB::table('customer_credits')
                ->where('company_id', $companyId)
                ->where('customer_id', $plan->customer_id)
                ->where('type', 'credit')
                ->sum('amount');

            $creditUsed = (float) DB::table('customer_credits')
                ->where('company_id', $companyId)
                ->where('customer_id', $plan->customer_id)
                ->where('type', 'debit')
                ->sum('amount');

            return response()->json([
                'msg' => 'Treatment plan cash summary',
                'status' => 200,
                'data' => [
                    'plan_id' => $plan->id,
                    'customer_id' => $plan->customer_id,
                    'cash' => [
                        'cash_in' => 0.0,
                        'cash_out_invoice_refunds' => 0.0,
                        'cash_out_credit_refunds' => 0.0,
                        'net_cash' => 0.0,
                    ],
                    'customer_credit_balance' => [
                        'credit_issued' => $creditIssued,
                        'credit_used' => $creditUsed,
                        'net_credit' => $creditIssued - $creditUsed,
                    ],
                ],
            ]);
        }

        // cash in = total received money on payments for those invoices
        $cashIn = (float) DB::table('payments')
            ->where('company_id', $companyId)
            ->whereIn('invoice_id', $invoiceIds)
            ->sum('amount');

        // cash out invoice refunds
        $cashOutInvoiceRefunds = (float) DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->where('payments.company_id', $companyId)
            ->where('payment_refunds.company_id', $companyId)
            ->whereIn('payments.invoice_id', $invoiceIds)
            ->where('payment_refunds.applies_to', 'invoice')
            ->sum('payment_refunds.amount');

        // cash out credit refunds (THIS is what we separate)
        $cashOutCreditRefunds = (float) DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->where('payments.company_id', $companyId)
            ->where('payment_refunds.company_id', $companyId)
            ->whereIn('payments.invoice_id', $invoiceIds)
            ->where('payment_refunds.applies_to', 'credit')
            ->sum('payment_refunds.amount');

        $netCash = $cashIn - ($cashOutInvoiceRefunds + $cashOutCreditRefunds);

        // wallet
        $creditIssued = (float) DB::table('customer_credits')
            ->where('company_id', $companyId)
            ->where('customer_id', $plan->customer_id)
            ->where('type', 'credit')
            ->sum('amount');

        $creditUsed = (float) DB::table('customer_credits')
            ->where('company_id', $companyId)
            ->where('customer_id', $plan->customer_id)
            ->where('type', 'debit')
            ->sum('amount');

        return response()->json([
            'msg' => 'Treatment plan cash summary',
            'status' => 200,
            'data' => [
                'plan_id' => $plan->id,
                'customer_id' => $plan->customer_id,
                'cash' => [
                    'cash_in' => $cashIn,
                    'cash_out_invoice_refunds' => $cashOutInvoiceRefunds,
                    'cash_out_credit_refunds' => $cashOutCreditRefunds,
                    'net_cash' => $netCash,
                ],
                'customer_credit_balance' => [
                    'credit_issued' => $creditIssued,
                    'credit_used' => $creditUsed,
                    'net_credit' => $creditIssued - $creditUsed,
                ],
            ],
        ]);
    }
}
