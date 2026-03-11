<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\TreatmentPlan;
use App\Models\Invoice;
use App\Models\Procedure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\TreatmentPlanItem;

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
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'title' => ['required', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],
        ]);

        $plan = TreatmentPlan::create([
            'company_id' => $companyId,
            'customer_id' => $data['customer_id'],
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'total_cost' => 0,
            'status' => 'active',
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
            'title' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,completed,cancelled'],
        ]);

        $plan->update([
            'title' => $data['title'] ?? $plan->title,
            'notes' => $data['notes'] ?? $plan->notes,
            'status' => $data['status'] ?? $plan->status,
        ]);

        return response()->json([
            'msg' => 'Treatment plan updated',
            'data' => $this->planResponse($plan->fresh()->load('customer'), $companyId),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $plan = TreatmentPlan::where('company_id', $companyId)->findOrFail($id);

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
            'id' => $plan->id,
            'customer_id' => $plan->customer_id,
            'title' => $plan->title,
            'notes' => $plan->notes,
            'total_cost' => (float) $plan->total_cost,
            'status' => $plan->status,
            'created_at' => $plan->created_at,
            'updated_at' => $plan->updated_at,

            'customer' => $plan->relationLoaded('customer') ? $plan->customer : null,

            'total_paid' => (float) $totalApplied,
            'total_refunded' => (float) $totalRefundedInvoice,
            'net_paid' => (float) $netPaid,
            'remaining' => (float) $remaining,
        ];

        if ($withInvoices) {
            $resp['invoices'] = $plan->relationLoaded('invoices') ? $plan->invoices : [];
        }

        return $resp;
    }

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
            $ref = (float) ($refundedByInvoice[$inv->id] ?? 0);
            $net = $paid - $ref;

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
        $totalPaid = (float) $invoiceRows->sum('total_paid');
        $totalRefunded = (float) $invoiceRows->sum('total_refunded');
        $netPaid = $totalPaid - $totalRefunded;
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

    public function cashSummary(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $plan = TreatmentPlan::where('company_id', $companyId)->findOrFail($id);

        $invoiceIds = Invoice::where('company_id', $companyId)
            ->where('treatment_plan_id', $plan->id)
            ->pluck('id');

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

        $cashIn = (float) DB::table('payments')
            ->where('company_id', $companyId)
            ->whereIn('invoice_id', $invoiceIds)
            ->sum('amount');

        $cashOutInvoiceRefunds = (float) DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->where('payments.company_id', $companyId)
            ->where('payment_refunds.company_id', $companyId)
            ->whereIn('payments.invoice_id', $invoiceIds)
            ->where('payment_refunds.applies_to', 'invoice')
            ->sum('payment_refunds.amount');

        $cashOutCreditRefunds = (float) DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->where('payments.company_id', $companyId)
            ->where('payment_refunds.company_id', $companyId)
            ->whereIn('payments.invoice_id', $invoiceIds)
            ->where('payment_refunds.applies_to', 'credit')
            ->sum('payment_refunds.amount');

        $netCash = $cashIn - ($cashOutInvoiceRefunds + $cashOutCreditRefunds);

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

    public function items(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $plan = TreatmentPlan::where('company_id', $companyId)->findOrFail($id);

        $items = TreatmentPlanItem::query()
            ->where('company_id', $companyId)
            ->where('treatment_plan_id', $plan->id)
            ->with('procedureRef:id,name,default_price')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'msg' => 'Treatment plan items',
            'status' => 200,
            'data' => $items,
        ]);
    }

    public function addItem(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $plan = TreatmentPlan::where('company_id', $companyId)->findOrFail($id);

        $data = $request->validate([
            'procedure_id' => ['required', 'integer'],
            'tooth_number' => ['nullable', 'string', 'max:10'],
            'surface' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $procedure = Procedure::where('company_id', $companyId)
            ->findOrFail($data['procedure_id']);

        $price = $data['price'] ?? $procedure->default_price;

        $item = TreatmentPlanItem::create([
            'company_id' => $companyId,
            'treatment_plan_id' => $plan->id,
            'procedure_id' => $procedure->id,
            'procedure' => $procedure->name,
            'tooth_number' => $data['tooth_number'] ?? null,
            'surface' => $data['surface'] ?? null,
            'notes' => $data['notes'] ?? null,
            'price' => $price,
        ]);

        $this->recalculatePlanTotal($companyId, $plan->id);

        return response()->json([
            'msg' => 'Item added',
            'status' => 201,
            'data' => $item->load('procedureRef'),
        ], 201);
    }

    public function updateItem(Request $request, $itemId)
    {
        $companyId = $request->user()->company_id;

        $item = TreatmentPlanItem::where('company_id', $companyId)->findOrFail($itemId);

        $data = $request->validate([
            'procedure_id' => ['sometimes', 'required', 'integer'],
            'tooth_number' => ['sometimes', 'nullable', 'string', 'max:10'],
            'surface' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        if (isset($data['procedure_id'])) {
            $procedure = Procedure::where('company_id', $companyId)
                ->findOrFail($data['procedure_id']);

            $item->procedure_id = $procedure->id;
            $item->procedure = $procedure->name;

            if (!isset($data['price'])) {
                $item->price = $procedure->default_price;
            }
        }

        $item->update([
            'tooth_number' => $data['tooth_number'] ?? $item->tooth_number,
            'surface' => $data['surface'] ?? $item->surface,
            'notes' => $data['notes'] ?? $item->notes,
            'price' => $data['price'] ?? $item->price,
        ]);

        $this->recalculatePlanTotal($companyId, $item->treatment_plan_id);

        return response()->json([
            'msg' => 'Item updated',
            'status' => 200,
            'data' => $item->fresh()->load('procedureRef'),
        ]);
    }

    public function deleteItem(Request $request, $itemId)
    {
        $companyId = $request->user()->company_id;

        $item = TreatmentPlanItem::where('company_id', $companyId)->findOrFail($itemId);
        $planId = $item->treatment_plan_id;

        $item->delete();

        $this->recalculatePlanTotal($companyId, $planId);

        return response()->json([
            'msg' => 'Item deleted',
            'status' => 200,
        ]);
    }

    private function recalculatePlanTotal(int $companyId, int $planId): void
    {
        $sum = (float) TreatmentPlanItem::query()
            ->where('company_id', $companyId)
            ->where('treatment_plan_id', $planId)
            ->sum('price');

        TreatmentPlan::query()
            ->where('company_id', $companyId)
            ->where('id', $planId)
            ->update([
                'total_cost' => $sum,
                'updated_at' => now(),
            ]);
    }

    public function startItem(Request $request, $itemId)
    {
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'doctor_id' => ['nullable', 'integer'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($request, $companyId, $itemId, $data) {
            $item = TreatmentPlanItem::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($itemId);

            if ($item->status === 'completed') {
                return response()->json([
                    'msg' => 'This procedure is already completed',
                    'status' => 409,
                ], 409);
            }

            if ($item->status === 'in_progress' && $item->appointment_id) {
                return response()->json([
                    'msg' => 'This procedure is already in progress',
                    'status' => 409,
                    'appointment_id' => $item->appointment_id,
                ], 409);
            }

            $plan = TreatmentPlan::query()
                ->where('company_id', $companyId)
                ->findOrFail($item->treatment_plan_id);

            if (!empty($data['doctor_id'])) {
                $doctor = \App\Models\Doctor::query()
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->findOrFail((int) $data['doctor_id']);
            } else {
                $doctor = \App\Models\Doctor::query()
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->orderBy('id', 'asc')
                    ->first();

                if (!$doctor) {
                    return response()->json([
                        'msg' => 'No active doctor found. Create a doctor first.',
                        'status' => 422,
                    ], 422);
                }
            }

            $date = \Carbon\Carbon::parse($data['appointment_date'])->toDateString();
            $time = $data['appointment_time'];

            $startTime = $doctor->work_start ?? '09:00';
            $endTime = $doctor->work_end ?? '17:00';
            $slotMinutes = (int) ($doctor->slot_minutes ?? 30);

            $start = \Carbon\Carbon::parse("$date $startTime");
            $end = \Carbon\Carbon::parse("$date $endTime");
            $requested = \Carbon\Carbon::parse("$date $time");

            if ($requested->lt($start) || $requested->gte($end)) {
                return response()->json([
                    'msg' => 'Time is outside working hours.',
                    'status' => 422,
                    'errors' => [
                        'appointment_time' => ['Time is outside working hours.'],
                    ],
                ], 422);
            }

            $diff = $start->diffInMinutes($requested);
            if ($slotMinutes <= 0 || ($diff % $slotMinutes !== 0)) {
                return response()->json([
                    'msg' => 'Time must match slot interval.',
                    'status' => 422,
                    'errors' => [
                        'appointment_time' => ['Time must match slot interval.'],
                    ],
                ], 422);
            }

            $existing = \App\Models\Appointment::query()
                ->where('company_id', $companyId)
                ->where('doctor_id', $doctor->id)
                ->whereDate('appointment_date', $date)
                ->whereTime('appointment_time', $time)
                ->lockForUpdate()
                ->first();

            if ($existing && in_array($existing->status, ['scheduled', 'completed', 'no_show'], true)) {
                return response()->json([
                    'msg' => 'Time slot already booked',
                    'status' => 422,
                    'errors' => [
                        'appointment_time' => ['This time slot is already booked for this doctor.'],
                    ],
                ], 422);
            }

            $appointment = \App\Models\Appointment::create([
                'company_id' => $companyId,
                'patient_id' => $plan->customer_id,
                'doctor_id' => $doctor->id,
                'doctor_name' => $doctor->name ?? 'Doctor',
                'appointment_date' => $date,
                'appointment_time' => $time,
                'status' => 'scheduled',
                'notes' => $data['notes'] ?? $item->notes,
                'created_by' => $request->user()->id,
            ]);

            $item->update([
                'status' => 'in_progress',
                'appointment_id' => $appointment->id,
                'started_at' => now(),
            ]);

            ActivityLogger::log(
                $companyId,
                $request->user(),
                'treatment_plan_item.started',
                TreatmentPlanItem::class,
                $item->id,
                [
                    'treatment_plan_id' => $item->treatment_plan_id,
                    'appointment_id' => $appointment->id,
                    'patient_id' => $plan->customer_id,
                    'doctor_id' => $doctor->id,
                    'procedure_id' => $item->procedure_id,
                    'procedure' => $item->procedure,
                    'date' => $date,
                    'time' => $time,
                ]
            );

            return response()->json([
                'msg' => 'Procedure started successfully',
                'status' => 201,
                'data' => [
                    'item' => $item->fresh(),
                    'appointment' => $appointment->load([
                        'patient:id,name,email,company_id',
                        'doctor:id,name,company_id,work_start,work_end,slot_minutes',
                    ]),
                ],
            ], 201);
        });
    }
}
