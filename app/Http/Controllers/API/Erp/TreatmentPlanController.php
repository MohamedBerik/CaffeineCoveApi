<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Procedure;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Services\ActivityLogger;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Traits\ValidatesAppointments;
use App\Traits\HandlesAppointmentReminders;
use Carbon\Carbon;

class TreatmentPlanController extends Controller
{
    use ValidatesAppointments, HandlesAppointmentReminders;

    public function __construct()
    {
        $this->authorizeResource(TreatmentPlan::class, 'treatmentPlan', [
            'except' => ['index', 'startItem', 'attachAppointment', 'store']
        ]);
    }

    public function index(Request $request)
    {
        $companyId = Tenant::id();

        $plans = TreatmentPlan::query()
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
        $companyId = Tenant::id();

        $plan = TreatmentPlan::query()
            ->with([
                'customer:id,name,email,company_id',
                'invoices' => function ($q) {
                    $q->with([
                        'items.product',
                        'payments.refunds',
                        'journalEntries.lines.account',
                    ])
                        ->orderByDesc('id');
                },
            ])
            ->findOrFail($id);

        return response()->json($this->planResponse($plan, $companyId, true));
    }

    public function store(Request $request)
    {
        $companyId = Tenant::id();

        $data = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id'),
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
        $companyId = Tenant::id();

        $plan = TreatmentPlan::query()->findOrFail($id);

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
        $companyId = Tenant::id();

        $plan = TreatmentPlan::query()->findOrFail($id);

        $hasInvoices = Invoice::query()
            ->where('treatment_plan_id', $plan->id)
            ->exists();

        if ($hasInvoices) {
            return response()->json([
                'msg' => 'Cannot delete plan with linked invoices',
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'msg' => 'Treatment plan deleted',
        ]);
    }

    private function planResponse(TreatmentPlan $plan, int $companyId, bool $withInvoices = false): array
    {
        $invoiceIds = $withInvoices && $plan->relationLoaded('invoices')
            ? $plan->invoices->pluck('id')->all()
            : Invoice::query()
            ->where('treatment_plan_id', $plan->id)
            ->pluck('id')
            ->all();

        $totalDirectPaid = 0.0;
        $totalRefundedInvoice = 0.0;
        $totalCreditApplied = 0.0;

        if (!empty($invoiceIds)) {
            $totalDirectPaid = (float) DB::table('payments')
                ->whereIn('invoice_id', $invoiceIds)
                ->sum('applied_amount');

            $totalRefundedInvoice = (float) DB::table('payment_refunds')
                ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
                ->whereIn('payments.invoice_id', $invoiceIds)
                ->where('payment_refunds.applies_to', 'invoice')
                ->sum('payment_refunds.amount');

            $totalCreditApplied = (float) DB::table('customer_credits')
                ->where('type', 'debit')
                ->whereIn('invoice_id', $invoiceIds)
                ->sum('amount');
        }

        $grossPaid = $totalDirectPaid + $totalCreditApplied;
        $netPaid = $grossPaid - $totalRefundedInvoice;
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

            'total_direct_paid' => (float) $totalDirectPaid,
            'total_credit_applied' => (float) $totalCreditApplied,
            'total_paid' => (float) $grossPaid,
            'total_refunded' => (float) $totalRefundedInvoice,
            'net_paid' => (float) $netPaid,
            'remaining' => (float) $remaining,
        ];

        if ($withInvoices) {
            $resp['invoices'] = $plan->relationLoaded('invoices')
                ? $plan->invoices
                : [];
        }

        return $resp;
    }

    public function summary(Request $request, $id)
    {
        $companyId = Tenant::id();

        $plan = TreatmentPlan::query()->findOrFail($id);

        $invoices = Invoice::query()
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
                        'direct_paid' => 0.0,
                        'credit_applied' => 0.0,
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
            ->whereIn('invoice_id', $invoiceIds)
            ->select('invoice_id', DB::raw('SUM(applied_amount) as total_paid'))
            ->groupBy('invoice_id')
            ->pluck('total_paid', 'invoice_id');

        $creditAppliedByInvoice = DB::table('customer_credits')
            ->where('customer_id', $plan->customer_id)
            ->whereIn('invoice_id', $invoiceIds)
            ->where('type', 'debit')
            ->select('invoice_id', DB::raw('SUM(amount) as total_credit_applied'))
            ->groupBy('invoice_id')
            ->pluck('total_credit_applied', 'invoice_id');

        $refundedByInvoice = DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->whereIn('payments.invoice_id', $invoiceIds)
            ->where('payment_refunds.applies_to', 'invoice')
            ->select('payments.invoice_id as invoice_id', DB::raw('SUM(payment_refunds.amount) as total_refunded'))
            ->groupBy('payments.invoice_id')
            ->pluck('total_refunded', 'invoice_id');

        $invoiceRows = $invoices->map(function ($inv) use ($paidByInvoice, $creditAppliedByInvoice, $refundedByInvoice) {
            $directPaid = (float) ($paidByInvoice[$inv->id] ?? 0);
            $creditApplied = (float) ($creditAppliedByInvoice[$inv->id] ?? 0);
            $ref = (float) ($refundedByInvoice[$inv->id] ?? 0);
            $totalPaid = $directPaid + $creditApplied;
            $net = $totalPaid - $ref;

            return [
                'id' => $inv->id,
                'number' => $inv->number,
                'customer_id' => $inv->customer_id,
                'appointment_id' => $inv->appointment_id,
                'total' => (float) $inv->total,
                'status' => $inv->status,
                'issued_at' => $inv->issued_at,

                'direct_paid' => $directPaid,
                'credit_applied' => $creditApplied,
                'total_paid' => $totalPaid,
                'total_refunded' => $ref,
                'net_paid' => $net,
                'remaining' => max(0, (float) $inv->total - $net),
            ];
        });

        $totalInvoiced = (float) $invoices->sum(fn($i) => (float) $i->total);
        $directPaid = (float) $invoiceRows->sum('direct_paid');
        $creditApplied = (float) $invoiceRows->sum('credit_applied');
        $totalPaid = (float) $invoiceRows->sum('total_paid');
        $totalRefunded = (float) $invoiceRows->sum('total_refunded');
        $netPaid = (float) $invoiceRows->sum('net_paid');
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
                    'direct_paid' => $directPaid,
                    'credit_applied' => $creditApplied,
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
        $companyId = Tenant::id();

        $plan = TreatmentPlan::query()->findOrFail($id);

        $invoiceIds = Invoice::query()
            ->where('treatment_plan_id', $plan->id)
            ->pluck('id');

        $creditIssued = (float) DB::table('customer_credits')
            ->where('customer_id', $plan->customer_id)
            ->where('type', 'credit')
            ->sum('amount');

        $creditUsed = (float) DB::table('customer_credits')
            ->where('customer_id', $plan->customer_id)
            ->where('type', 'debit')
            ->sum('amount');

        if ($invoiceIds->isEmpty()) {
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
                        'net_credit' => max(0, $creditIssued - $creditUsed),
                    ],
                ],
            ]);
        }

        $cashIn = (float) DB::table('payments')
            ->whereIn('invoice_id', $invoiceIds)
            ->sum('amount');

        $cashOutInvoiceRefunds = (float) DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->whereIn('payments.invoice_id', $invoiceIds)
            ->where('payment_refunds.applies_to', 'invoice')
            ->sum('payment_refunds.amount');

        $cashOutCreditRefunds = (float) DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->whereIn('payments.invoice_id', $invoiceIds)
            ->where('payment_refunds.applies_to', 'credit')
            ->sum('payment_refunds.amount');

        $netCash = $cashIn - ($cashOutInvoiceRefunds + $cashOutCreditRefunds);

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
                    'net_credit' => max(0, $creditIssued - $creditUsed),
                ],
            ],
        ]);
    }

    private function recalculatePlanTotal(int $planId): void
    {
        $sum = (float) TreatmentPlanItem::query()
            ->where('treatment_plan_id', $planId)
            ->sum('price');

        TreatmentPlan::query()
            ->where('id', $planId)
            ->update([
                'total_cost' => $sum,
                'updated_at' => now(),
            ]);
    }

    public function items(Request $request, $planId)
    {
        $plan = TreatmentPlan::query()->findOrFail($planId);

        $items = TreatmentPlanItem::query()
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

    public function addItem(Request $request, $planId)
    {
        $companyId = Tenant::id();

        $plan = TreatmentPlan::query()->findOrFail($planId);

        $data = $request->validate([
            'procedure_id' => ['required', 'integer'],
            'tooth_number' => ['nullable', 'string', 'max:10'],
            'surface' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'planned_sessions' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $procedure = Procedure::query()->findOrFail($data['procedure_id']);

        $price = $data['price'] ?? $procedure->default_price;
        $plannedSessions = (int) ($data['planned_sessions'] ?? 1);

        $item = TreatmentPlanItem::create([
            'company_id' => $companyId,
            'treatment_plan_id' => $plan->id,
            'procedure_id' => $procedure->id,
            'procedure' => $procedure->name,
            'tooth_number' => $data['tooth_number'] ?? null,
            'surface' => $data['surface'] ?? null,
            'notes' => $data['notes'] ?? null,
            'price' => $price,
            'planned_sessions' => $plannedSessions,
            'completed_sessions' => 0,
            'status' => 'planned',
        ]);

        $this->recalculatePlanTotal($plan->id);

        return response()->json([
            'msg' => 'Item added',
            'status' => 201,
            'data' => $item->load('procedureRef'),
        ], 201);
    }

    public function updateItem(Request $request, $itemId)
    {
        $companyId = Tenant::id();

        $item = TreatmentPlanItem::query()->findOrFail($itemId);

        $data = $request->validate([
            'procedure_id' => ['sometimes', 'required', 'integer'],
            'tooth_number' => ['sometimes', 'nullable', 'string', 'max:10'],
            'surface' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'planned_sessions' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        if (isset($data['procedure_id'])) {
            $procedure = Procedure::query()->findOrFail($data['procedure_id']);
            $item->procedure_id = $procedure->id;
            $item->procedure = $procedure->name;

            if (!array_key_exists('price', $data)) {
                $item->price = $procedure->default_price;
            }
        }

        if (isset($data['planned_sessions'])) {
            if ((int) $item->completed_sessions > 0) {
                return response()->json([
                    'msg' => 'Cannot change sessions after treatment has started',
                    'status' => 422,
                ], 422);
            }
            $item->planned_sessions = (int) $data['planned_sessions'];
        }

        $item->update([
            'tooth_number' => array_key_exists('tooth_number', $data) ? $data['tooth_number'] : $item->tooth_number,
            'surface' => array_key_exists('surface', $data) ? $data['surface'] : $item->surface,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $item->notes,
            'price' => array_key_exists('price', $data) ? $data['price'] : $item->price,
            'planned_sessions' => $item->planned_sessions,
        ]);

        $this->recalculatePlanTotal($item->treatment_plan_id);

        return response()->json([
            'msg' => 'Item updated',
            'status' => 200,
            'data' => $item->fresh()->load('procedureRef'),
        ]);
    }

    public function deleteItem(Request $request, $itemId)
    {
        $item = TreatmentPlanItem::query()->findOrFail($itemId);

        if ((int) ($item->completed_sessions ?? 0) > 0 || !empty($item->appointment_id)) {
            return response()->json([
                'msg' => 'Cannot delete item after treatment has started',
                'status' => 422,
            ], 422);
        }

        $planId = $item->treatment_plan_id;
        $item->delete();
        $this->recalculatePlanTotal($planId);

        return response()->json([
            'msg' => 'Item deleted',
            'status' => 200,
        ]);
    }

    public function startItem(Request $request, $itemId)
    {

        $companyId = Tenant::id();

        $data = $request->validate([
            'doctor_id' => ['nullable', 'integer'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($request, $companyId, $itemId, $data) {
            $item = TreatmentPlanItem::query()
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

            $plan = TreatmentPlan::query()->findOrFail($item->treatment_plan_id);

            $this->authorize('startItem', $plan);

            if (!empty($data['doctor_id'])) {
                $doctor = Doctor::query()
                    ->where('is_active', true)
                    ->findOrFail((int) $data['doctor_id']);
            } else {
                $doctor = Doctor::query()
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

            $date = Carbon::parse($data['appointment_date'])->toDateString();
            $time = $data['appointment_time'];

            $this->validateAppointmentDateTime($doctor, $date, $time);

            $existing = Appointment::query()
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

            $appointment = Appointment::create([
                'company_id' => $companyId,
                'branch_id' => Tenant::branchId() ?? $request->user()->branch_id,
                'patient_id' => $plan->customer_id,
                'doctor_id' => $doctor->id,
                'doctor_name' => $doctor->name ?? 'Doctor',
                'appointment_date' => $date,
                'appointment_time' => $time,
                'appointment_type' => 'treatment',
                'status' => 'scheduled',
                'notes' => $data['notes'] ?? $item->notes,
                'created_by' => $request->user()->id,
                ...$this->buildPendingReminder($date, $time),
            ]);

            $item->update([
                'status' => 'in_progress',
                'appointment_id' => $appointment->id,
                'started_at' => now(),
            ]);

            ActivityLogger::log(
                $companyId,
                $request->user(),
                'appointment.booked',
                Appointment::class,
                $appointment->id,
                [
                    'doctor_id' => $doctor->id,
                    'patient_id' => $plan->customer_id,
                    'date' => $date,
                    'time' => $time,
                    'appointment_type' => 'treatment',
                    'treatment_plan_id' => $item->treatment_plan_id,
                    'treatment_plan_item_id' => $item->id,
                    'procedure_id' => $item->procedure_id,
                    'procedure' => $item->procedure,
                ]
            );

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

    public function attachAppointment(Request $request, $itemId)
    {
        $item = TreatmentPlanItem::query()
            ->with('plan')
            ->findOrFail($itemId);

        $data = $request->validate([
            'appointment_id' => [
                'required',
                'integer',
                Rule::exists('appointments', 'id'),
            ],
        ]);

        $appointment = Appointment::query()->findOrFail($data['appointment_id']);

        $this->authorize('attachAppointment', $item->plan);

        if (!$item->plan) {
            return response()->json([
                'msg' => 'Treatment plan item has no parent plan',
                'status' => 422,
            ], 422);
        }

        if ((int) $appointment->patient_id !== (int) $item->plan->customer_id) {
            return response()->json([
                'msg' => 'Appointment does not belong to this patient',
                'status' => 422,
                'errors' => [
                    'appointment_id' => ['Appointment patient mismatch.'],
                ],
            ], 422);
        }

        if ($appointment->status !== 'scheduled') {
            return response()->json([
                'msg' => 'Only scheduled appointments can be linked',
                'status' => 422,
                'errors' => [
                    'appointment_id' => ['You can only link a treatment item to a scheduled appointment.'],
                ],
            ], 422);
        }

        $appointmentDateTime = Carbon::parse($appointment->appointment_date . ' ' . $appointment->appointment_time)->startOfMinute();

        if ($appointmentDateTime->lte(now()->startOfMinute())) {
            return response()->json([
                'msg' => 'Cannot link to a past or ongoing appointment',
                'status' => 422,
                'errors' => [
                    'appointment_id' => ['You can only link to a future scheduled appointment.'],
                ],
            ], 422);
        }

        $existingInvoice = Invoice::query()
            ->where('appointment_id', $appointment->id)
            ->exists();

        if ($existingInvoice) {
            return response()->json([
                'msg' => 'This appointment already has an invoice',
                'status' => 422,
                'errors' => [
                    'appointment_id' => ['Cannot link to an appointment that has already been invoiced.'],
                ],
            ], 422);
        }

        $appointmentAlreadyLinked = TreatmentPlanItem::query()
            ->where('appointment_id', $appointment->id)
            ->where('id', '!=', $item->id)
            ->exists();

        if ($appointmentAlreadyLinked) {
            return response()->json([
                'msg' => 'This appointment is already linked to another treatment item',
                'status' => 422,
                'errors' => [
                    'appointment_id' => ['This appointment is already linked to another treatment item.'],
                ],
            ], 422);
        }

        if (!empty($item->appointment_id) && (int) $item->appointment_id !== (int) $appointment->id) {
            return response()->json([
                'msg' => 'This treatment item is already linked to another appointment',
                'status' => 422,
                'errors' => [
                    'appointment_id' => ['This treatment item is already linked to another appointment.'],
                ],
            ], 422);
        }

        $appointment->update([
            'appointment_type' => 'treatment',
        ]);

        $item->update([
            'appointment_id' => $appointment->id,
            'status' => $item->status === 'planned' ? 'in_progress' : $item->status,
            'started_at' => $item->started_at ?? now(),
        ]);

        return response()->json([
            'msg' => 'Treatment plan item attached to appointment successfully',
            'status' => 200,
            'data' => $item->fresh()->load([
                'plan:id,customer_id',
                'appointment:id,patient_id,appointment_date,appointment_time,status',
                'procedureRef:id,name,default_price',
            ]),
        ]);
    }
}
