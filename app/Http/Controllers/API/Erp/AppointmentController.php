<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CustomerLedgerEntry;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\TreatmentPlan;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = Appointment::query()
            ->where('company_id', $companyId)
            ->with([
                'patient:id,name,email,company_id',
                'doctor:id,name,company_id,work_start,work_end,slot_minutes',
            ])
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time');

        // ✅ safe scoped search (no leakage due to OR)
        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('doctor_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('patient', function ($p) use ($search) {
                        $p->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = (int) ($request->get('per_page', 20));
        $data = $query->paginate($perPage);

        return response()->json([
            'msg' => 'Appointments list',
            'status' => 200,
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ]);
    }

    /**
     * ERP-style create (admin/internal)
     * ✅ required doctor_id + tenant scoped patient/doctor
     * ✅ collision check by doctor_id + whereTime
     * ✅ REBOOK if existing slot is cancelled (update same row)
     */
    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $v = Validator::make($request->all(), [
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'doctor_id' => [
                'required',
                'integer',
                Rule::exists('doctors', 'id')->where(fn($q) => $q->where('company_id', $companyId)->where('is_active', true)),
            ],
            'doctor_name' => ['nullable', 'string', 'max:190'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'status' => ['nullable', Rule::in(['scheduled', 'completed', 'cancelled', 'no_show'])],
            'notes' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'msg' => 'Validation required',
                'status' => 422,
                'errors' => $v->errors(),
            ], 422);
        }

        $data = $v->validated();
        $date = Carbon::parse($data['appointment_date'])->toDateString();
        $time = $data['appointment_time'];

        $doctor = Doctor::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail((int) $data['doctor_id']);

        $doctorName = trim((string)($data['doctor_name'] ?? '')) ?: ($doctor->name ?? 'Doctor');

        $blockedStatuses = ['scheduled', 'completed', 'no_show'];
        $requestedStatus = $data['status'] ?? 'scheduled';

        return DB::transaction(function () use (
            $request,
            $companyId,
            $data,
            $date,
            $time,
            $doctor,
            $doctorName,
            $blockedStatuses,
            $requestedStatus
        ) {
            $existing = Appointment::query()
                ->where('company_id', $companyId)
                ->where('doctor_id', $doctor->id)
                ->whereDate('appointment_date', $date)
                ->whereTime('appointment_time', $time)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (in_array($existing->status, $blockedStatuses, true)) {
                    return response()->json([
                        'msg' => 'Time slot already booked',
                        'status' => 422,
                        'errors' => [
                            'appointment_time' => ['This time slot is already booked for this doctor.'],
                        ],
                    ], 422);
                }

                if ($existing->status === 'cancelled') {
                    $existing->update([
                        'patient_id' => $data['patient_id'],
                        'doctor_name' => $doctorName,
                        'status' => $requestedStatus, // غالباً scheduled
                        'notes' => $data['notes'] ?? null,
                        'created_by' => $request->user()->id,
                        'appointment_date' => $date,
                        'appointment_time' => $time,
                    ]);

                    // ✅ log rebook (FIX: use $existing not $appointment)
                    ActivityLogger::log(
                        $companyId,
                        $request->user(),
                        'appointment.rebooked',
                        Appointment::class,
                        $existing->id,
                        [
                            'doctor_id'  => $existing->doctor_id,
                            'patient_id' => $existing->patient_id,
                            'date'       => Carbon::parse($existing->appointment_date)->toDateString(),
                            'time'       => substr((string) $existing->appointment_time, 0, 5),
                        ]
                    );

                    return response()->json([
                        'msg' => 'Appointment rebooked',
                        'status' => 200,
                        'data' => $existing->fresh()->load([
                            'patient:id,name,email,company_id',
                            'doctor:id,name,company_id,work_start,work_end,slot_minutes',
                        ]),
                    ], 200);
                }
            }

            try {
                $appointment = Appointment::create([
                    'company_id' => $companyId,
                    'patient_id' => $data['patient_id'],
                    'doctor_id'  => $doctor->id,
                    'doctor_name' => $doctorName,
                    'appointment_date' => $date,
                    'appointment_time' => $time,
                    'status' => $requestedStatus,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
            } catch (QueryException $e) {
                if ((string) $e->getCode() === '23000') {
                    return response()->json([
                        'msg' => 'Time slot already booked',
                        'status' => 422,
                        'errors' => [
                            'appointment_time' => ['This time slot is already booked for this doctor.'],
                        ],
                    ], 422);
                }
                throw $e;
            }

            // ✅ log created (ERP)
            ActivityLogger::log(
                $companyId,
                $request->user(),
                'appointment.created',
                Appointment::class,
                $appointment->id,
                [
                    'doctor_id'  => $appointment->doctor_id,
                    'patient_id' => $appointment->patient_id,
                    'date'       => Carbon::parse($appointment->appointment_date)->toDateString(),
                    'time'       => substr((string) $appointment->appointment_time, 0, 5),
                    'status'     => $appointment->status,
                ]
            );

            return response()->json([
                'msg' => 'Appointment created',
                'status' => 201,
                'data' => $appointment->load([
                    'patient:id,name,email,company_id',
                    'doctor:id,name,company_id,work_start,work_end,slot_minutes',
                ]),
            ], 201);
        });
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->with([
                'patient:id,name,email,company_id',
                'doctor:id,name,company_id,work_start,work_end,slot_minutes',
            ])
            ->findOrFail($id);

        return response()->json([
            'msg' => 'Appointment details',
            'status' => 200,
            'data' => $appointment,
        ]);
    }

    /**
     * ✅ update supports doctor_id and uses whereTime collision check
     * ✅ keeps doctor_name stable unless doctor_id changed or doctor_name explicitly emptied
     * ✅ logs appointment.updated
     */
    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        // ✅ A: لا تعديل على completed
        if ($appointment->status === 'completed') {
            return response()->json([
                'msg' => 'Completed appointments cannot be modified.',
                'status' => 422,
                'errors' => [
                    'status' => ['Completed appointments cannot be modified.'],
                ],
            ], 422);
        }

        // ✅ A: update بسيط (notes/status فقط)
        $v = Validator::make($request->all(), [
            'notes'  => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['scheduled', 'cancelled', 'no_show'])],
        ]);

        if ($v->fails()) {
            return response()->json([
                'msg' => 'Validation required',
                'status' => 422,
                'errors' => $v->errors(),
            ], 422);
        }

        $data = $v->validated();

        // ✅ track فعلي للحقول المسموح تعديلها
        $track = ['notes', 'status'];
        $before = $appointment->only($track);

        try {
            $appointment->update($data);
        } catch (QueryException $e) {
            // هنا غالباً مفيش 23000 أصلاً لأننا لا نغير slot
            throw $e;
        }

        $appointment->refresh();
        $after = $appointment->only($track);

        $changedFields = [];
        foreach ($track as $k) {
            if ((string)($before[$k] ?? '') !== (string)($after[$k] ?? '')) {
                $changedFields[] = $k;
            }
        }

        // ✅ log updated
        ActivityLogger::log(
            $companyId,
            $request->user(),
            'appointment.updated',
            Appointment::class,
            $appointment->id,
            [
                'changed_fields' => $changedFields,
                'doctor_id'      => $appointment->doctor_id,
                'patient_id'     => $appointment->patient_id,
                'date'           => Carbon::parse($appointment->appointment_date)->toDateString(),
                'time'           => substr((string) $appointment->appointment_time, 0, 5),
                'status'         => $appointment->status,
            ]
        );

        return response()->json([
            'msg' => 'Appointment updated',
            'status' => 200,
            'data' => $appointment->load([
                'patient:id,name,email,company_id',
                'doctor:id,name,company_id,work_start,work_end,slot_minutes',
            ]),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $appointment->delete();

        return response()->json([
            'msg' => 'Appointment deleted',
            'status' => 200,
            'data' => null,
        ]);
    }

    /**
     * Public booking endpoint
     * ✅ doctor_id optional
     * ✅ validates within working hours + slot interval
     * ✅ collision check uses doctor_id + whereTime
     * ✅ REBOOK cancelled slot by updating same record
     * ✅ logs appointment.booked OR appointment.rebooked (no duplicates)
     */
    public function book(Request $request)
    {
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'doctor_id' => ['nullable', 'integer'],
            'doctor_name' => ['nullable', 'string', 'max:190'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string'],
        ]);

        $date = Carbon::parse($data['appointment_date'])->toDateString();
        $time = $data['appointment_time'];

        // resolve doctor
        if (!empty($data['doctor_id'])) {
            $doctor = Doctor::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->findOrFail((int)$data['doctor_id']);
        } else {
            $doctor = Doctor::query()
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

        $doctorId = (int) $doctor->id;

        // working settings
        $startTime   = $doctor->work_start ?? '09:00';
        $endTime     = $doctor->work_end ?? '17:00';
        $slotMinutes = (int) ($doctor->slot_minutes ?? 30);

        $start = Carbon::parse("$date $startTime");
        $end   = Carbon::parse("$date $endTime");
        $requested = Carbon::parse("$date $time");

        if ($requested->lt($start) || $requested->gte($end)) {
            throw ValidationException::withMessages([
                'appointment_time' => ['Time is outside working hours.'],
            ]);
        }

        $diff = $start->diffInMinutes($requested);
        if ($slotMinutes <= 0 || ($diff % $slotMinutes !== 0)) {
            throw ValidationException::withMessages([
                'appointment_time' => ['Time must match slot interval.'],
            ]);
        }

        $doctorName = trim((string)($data['doctor_name'] ?? '')) ?: ($doctor->name ?? 'Doctor');
        $blockedStatuses = ['scheduled', 'completed', 'no_show'];

        return DB::transaction(function () use ($request, $companyId, $data, $date, $time, $doctorId, $doctorName, $blockedStatuses) {

            $existing = Appointment::query()
                ->where('company_id', $companyId)
                ->where('doctor_id', $doctorId)
                ->whereDate('appointment_date', $date)
                ->whereTime('appointment_time', $time)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (in_array($existing->status, $blockedStatuses, true)) {
                    return response()->json([
                        'msg' => 'Time slot already booked',
                        'status' => 422,
                        'errors' => [
                            'appointment_time' => ['This time slot is already booked for this doctor.'],
                        ],
                    ], 422);
                }

                if ($existing->status === 'cancelled') {
                    $existing->update([
                        'patient_id' => $data['patient_id'],
                        'doctor_name' => $doctorName,
                        'status' => 'scheduled',
                        'notes' => $data['notes'] ?? null,
                        'created_by' => $request->user()->id,
                        'appointment_date' => $date,
                        'appointment_time' => $time,
                    ]);

                    // ✅ log rebook (FIX: use $existing not $appointment)
                    ActivityLogger::log(
                        $companyId,
                        $request->user(),
                        'appointment.rebooked',
                        Appointment::class,
                        $existing->id,
                        [
                            'doctor_id'  => $existing->doctor_id,
                            'patient_id' => $existing->patient_id,
                            'date'       => Carbon::parse($existing->appointment_date)->toDateString(),
                            'time'       => substr((string) $existing->appointment_time, 0, 5),
                        ]
                    );

                    return response()->json([
                        'msg' => 'Appointment rebooked',
                        'status' => 200,
                        'data' => $existing->fresh()->load([
                            'patient:id,name,email,company_id',
                            'doctor:id,name,company_id,work_start,work_end,slot_minutes',
                        ]),
                    ], 200);
                }
            }

            try {
                $appointment = Appointment::create([
                    'company_id' => $companyId,
                    'patient_id' => $data['patient_id'],
                    'doctor_id'  => $doctorId,
                    'doctor_name' => $doctorName,
                    'appointment_date' => $date,
                    'appointment_time' => $time,
                    'status' => 'scheduled',
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
            } catch (QueryException $e) {
                if ((string) $e->getCode() === '23000') {
                    return response()->json([
                        'msg' => 'Time slot already booked',
                        'status' => 422,
                        'errors' => [
                            'appointment_time' => ['This time slot is already booked for this doctor.'],
                        ],
                    ], 422);
                }
                throw $e;
            }

            // ✅ log booked (single source of truth)
            ActivityLogger::log(
                $companyId,
                $request->user(),
                'appointment.booked',
                Appointment::class,
                $appointment->id,
                [
                    'doctor_id'  => $appointment->doctor_id,
                    'patient_id' => $appointment->patient_id,
                    'date'       => Carbon::parse($appointment->appointment_date)->toDateString(),
                    'time'       => substr((string) $appointment->appointment_time, 0, 5),
                ]
            );

            return response()->json([
                'msg' => 'Appointment booked',
                'status' => 201,
                'data' => $appointment->load([
                    'patient:id,name,email,company_id',
                    'doctor:id,name,company_id,work_start,work_end,slot_minutes',
                ]),
            ], 201);
        });
    }

    public function cancel(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        if ($appointment->status === 'completed') {
            throw ValidationException::withMessages([
                'status' => ['Completed appointments cannot be cancelled.'],
            ]);
        }

        $oldStatus = $appointment->status;

        $appointment->update(['status' => 'cancelled']);

        ActivityLogger::log(
            $companyId,
            $request->user(),
            'appointment.cancelled',
            Appointment::class,
            $appointment->id,
            [
                'old_status' => $oldStatus,
                'new_status' => 'cancelled',
                'doctor_id'  => $appointment->doctor_id,
                'patient_id' => $appointment->patient_id,
                'date'       => Carbon::parse($appointment->appointment_date)->toDateString(),
                'time'       => substr((string) $appointment->appointment_time, 0, 5),
            ]
        );

        return response()->json([
            'msg' => 'Appointment cancelled',
            'status' => 200,
            'data' => $appointment->fresh()->load([
                'patient:id,name,email,company_id',
                'doctor:id,name,company_id,work_start,work_end,slot_minutes',
            ]),
        ]);
    }

    /**
     * COMPLETE APPOINTMENT + CREATE INVOICE + (optional treatment_plan_id)
     * ✅ locks appointment, prevents duplicate invoices
     * ✅ validates treatment plan belongs to same customer
     * ✅ logs appointment.completed
     */
    public function complete(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $data = $request->validate([
            'total' => ['nullable', 'numeric', 'min:0.01'],
            'doctor_name' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],
            'treatment_plan_id' => [
                'nullable',
                'integer',
                Rule::exists('treatment_plans', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        $serviceProduct = \App\Models\Product::where('company_id', $companyId)
            ->where('title_en', 'Consultation')
            ->first();

        if (!$serviceProduct) {
            return response()->json([
                'msg' => 'Missing service product (Consultation). Create it first.',
                'status' => 422,
            ], 422);
        }

        return DB::transaction(function () use ($request, $companyId, $data, $appointment, $serviceProduct) {
            $appointment = Appointment::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            $existingInvoice = Invoice::query()
                ->where('company_id', $companyId)
                ->where('appointment_id', $appointment->id)
                ->lockForUpdate()
                ->first();

            if ($existingInvoice) {
                return response()->json([
                    'msg' => 'Invoice already exists for this appointment',
                    'status' => 409,
                    'invoice_id' => $existingInvoice->id,
                    'invoice_number' => $existingInvoice->number,
                    'treatment_plan_id' => $existingInvoice->treatment_plan_id,
                ], 409);
            }

            if ($appointment->status === 'completed') {
                return response()->json([
                    'msg' => 'Appointment already completed',
                    'status' => 409,
                ], 409);
            }

            // ✅ مهم: لا نسمح بإكمال إلا المواعيد scheduled فقط
            if (!in_array($appointment->status, ['scheduled'], true)) {
                return response()->json([
                    'msg' => 'Only scheduled appointments can be completed',
                    'status' => 422,
                    'errors' => [
                        'status' => ['Cancelled or no-show appointments cannot be completed directly.'],
                    ],
                ], 422);
            }

            $planId = $data['treatment_plan_id'] ?? null;
            $plan = null;

            if ($planId) {
                $plan = TreatmentPlan::query()
                    ->where('company_id', $companyId)
                    ->findOrFail($planId);

                if ((int) $plan->customer_id !== (int) $appointment->patient_id) {
                    return response()->json([
                        'msg' => 'Treatment plan does not belong to this customer',
                        'status' => 422,
                        'errors' => [
                            'treatment_plan_id' => ['Treatment plan customer_id mismatch.'],
                        ],
                    ], 422);
                }
            }

            $appointment->update([
                'doctor_name' => $data['doctor_name'] ?? $appointment->doctor_name,
                'notes' => $data['notes'] ?? $appointment->notes,
            ]);

            $lines = [];

            if ($plan) {
                $items = \App\Models\TreatmentPlanItem::query()
                    ->where('company_id', $companyId)
                    ->where('treatment_plan_id', $plan->id)
                    ->orderBy('id', 'asc')
                    ->get();

                if ($items->isEmpty()) {
                    return response()->json([
                        'msg' => 'Treatment plan has no items',
                        'status' => 422,
                        'errors' => [
                            'treatment_plan_id' => ['Treatment plan must have at least 1 item to complete appointment.'],
                        ],
                    ], 422);
                }

                foreach ($items as $it) {
                    $price = (float) $it->price;

                    $lines[] = [
                        'product_id' => $serviceProduct->id,
                        'quantity' => 1,
                        'unit_price' => $price,
                        'total' => $price,
                        'desc' => $it->procedure ?? 'Treatment Item',
                    ];
                }
            } else {
                $total = (float) ($data['total'] ?? 0);

                if ($total <= 0) {
                    return response()->json([
                        'msg' => 'total is required when no treatment_plan_id is provided',
                        'status' => 422,
                        'errors' => [
                            'total' => ['total is required when no treatment_plan_id is provided'],
                        ],
                    ], 422);
                }

                $lines[] = [
                    'product_id' => $serviceProduct->id,
                    'quantity' => 1,
                    'unit_price' => $total,
                    'total' => $total,
                    'desc' => 'Consultation',
                ];
            }

            $grandTotal = array_sum(array_map(fn($l) => (float) $l['total'], $lines));

            $order = \App\Models\Order::create([
                'company_id' => $companyId,
                'customer_id' => $appointment->patient_id,
                'title_en' => 'Appointment Services',
                'title_ar' => 'خدمات الموعد',
                'status' => 'confirmed',
                'total' => $grandTotal,
                'created_by' => $request->user()->id,
            ]);

            foreach ($lines as $l) {
                \App\Models\OrderItem::create([
                    'company_id' => $companyId,
                    'order_id' => $order->id,
                    'product_id' => $l['product_id'],
                    'quantity' => $l['quantity'],
                    'unit_price' => $l['unit_price'],
                    'total' => $l['total'],
                ]);
            }

            $number = \App\Services\InvoiceNumberService::generate($companyId);

            $invoice = \App\Models\Invoice::create([
                'company_id' => $companyId,
                'number' => $number,
                'order_id' => $order->id,
                'appointment_id' => $appointment->id,
                'treatment_plan_id' => $plan ? $plan->id : null,
                'customer_id' => $appointment->patient_id,
                'total' => $grandTotal,
                'status' => 'unpaid',
                'issued_at' => now(),
            ]);

            foreach ($lines as $l) {
                \App\Models\InvoiceItem::create([
                    'company_id' => $companyId,
                    'invoice_id' => $invoice->id,
                    'product_id' => $l['product_id'],
                    'quantity' => $l['quantity'],
                    'unit_price' => $l['unit_price'],
                    'total' => $l['total'],
                ]);
            }

            $exists = CustomerLedgerEntry::query()
                ->where('company_id', $companyId)
                ->where('invoice_id', $invoice->id)
                ->where('type', 'invoice')
                ->exists();

            if (!$exists) {
                CustomerLedgerEntry::create([
                    'company_id' => $companyId,
                    'customer_id' => $invoice->customer_id,
                    'invoice_id' => $invoice->id,
                    'payment_id' => null,
                    'refund_id' => null,
                    'type' => 'invoice',
                    'debit' => $invoice->total,
                    'credit' => 0,
                    'entry_date' => $invoice->issued_at ?? now(),
                    'description' => 'Invoice issued #' . $invoice->number,
                ]);
            }

            $appointment->update(['status' => 'completed']);

            ActivityLogger::log(
                $companyId,
                $request->user(),
                'appointment.completed',
                Appointment::class,
                $appointment->id,
                [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'treatment_plan_id' => $invoice->treatment_plan_id,
                    'total' => (float) $invoice->total,
                ]
            );

            return response()->json([
                'msg' => 'Appointment completed and invoice created',
                'status' => 200,
                'invoice_id' => $invoice->id,
                'order_id' => $order->id,
                'invoice_number' => $invoice->number,
                'treatment_plan_id' => $invoice->treatment_plan_id,
                'total' => (float) $invoice->total,
            ]);
        });
    }

    public function noShow(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        if ($appointment->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'status' => ['Only scheduled appointments can be marked as no-show.'],
            ]);
        }

        $oldStatus = $appointment->status;

        $appointment->update(['status' => 'no_show']);

        ActivityLogger::log(
            $companyId,
            $request->user(),
            'appointment.no_show',
            Appointment::class,
            $appointment->id,
            [
                'old_status' => $oldStatus,
                'new_status' => 'no_show',
                'doctor_id'  => $appointment->doctor_id,
                'patient_id' => $appointment->patient_id,
                'date'       => Carbon::parse($appointment->appointment_date)->toDateString(),
                'time'       => substr((string) $appointment->appointment_time, 0, 5),
            ]
        );

        return response()->json([
            'msg' => 'Appointment marked as no-show',
            'status' => 200,
            'data' => $appointment->fresh()->load([
                'patient:id,name,email,company_id',
                'doctor:id,name,company_id,work_start,work_end,slot_minutes',
            ]),
        ]);
    }

    public function reschedule(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        if ($appointment->status === 'completed') {
            return response()->json([
                'msg' => 'Completed appointments cannot be rescheduled.',
                'status' => 422,
            ], 422);
        }

        $data = $request->validate([
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'doctor_id' => [
                'required',
                'integer',
                Rule::exists('doctors', 'id')->where(
                    fn($q) => $q->where('company_id', $companyId)->where('is_active', true)
                ),
            ],
        ]);

        $newDate     = Carbon::parse($data['appointment_date'])->toDateString();
        $newTime     = $data['appointment_time']; // H:i
        $newDoctorId = (int) $data['doctor_id'];

        // ✅ Validate against target doctor's working hours + slot interval
        $doctor = Doctor::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail($newDoctorId);

        $workStart   = $doctor->work_start ?? '09:00';
        $workEnd     = $doctor->work_end ?? '17:00';
        $slotMinutes = (int) ($doctor->slot_minutes ?? 30);

        if ($slotMinutes <= 0) {
            return response()->json([
                'msg' => 'Invalid doctor slot configuration',
                'status' => 422,
                'errors' => [
                    'slot_minutes' => ['slot_minutes must be > 0'],
                ],
            ], 422);
        }

        $start     = Carbon::parse("$newDate $workStart");
        $end       = Carbon::parse("$newDate $workEnd");
        $requested = Carbon::parse("$newDate $newTime");

        if ($end->lte($start)) {
            return response()->json([
                'msg' => 'Invalid doctor working hours',
                'status' => 422,
                'errors' => [
                    'work_hours' => ['work_end must be after work_start'],
                ],
            ], 422);
        }

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
        if ($diff % $slotMinutes !== 0) {
            return response()->json([
                'msg' => 'Time must match slot interval.',
                'status' => 422,
                'errors' => [
                    'appointment_time' => ['Time must match slot interval.'],
                ],
            ], 422);
        }

        $blockedStatuses = ['scheduled', 'completed', 'no_show'];

        return DB::transaction(function () use (
            $request,
            $companyId,
            $appointment,
            $newDoctorId,
            $newDate,
            $newTime,
            $blockedStatuses,
            $doctor
        ) {
            // 1) Lock current appointment row
            $from = Appointment::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            if ($from->status === 'completed') {
                return response()->json([
                    'msg' => 'Completed appointments cannot be rescheduled.',
                    'status' => 422,
                ], 422);
            }

            // No-op: same slot
            $fromDate = Carbon::parse($from->appointment_date)->toDateString();
            $fromTime = $from->appointment_time ? substr((string) $from->appointment_time, 0, 5) : null;

            if ((int) $from->doctor_id === (int) $newDoctorId && $fromDate === $newDate && $fromTime === $newTime) {
                return response()->json([
                    'msg' => 'Appointment already in the requested slot',
                    'status' => 200,
                    'data' => $from->fresh()->load([
                        'patient:id,name,email,company_id',
                        'doctor:id,name,company_id,work_start,work_end,slot_minutes',
                    ]),
                ], 200);
            }

            // 2) Lock target slot row (if any)
            $to = Appointment::query()
                ->where('company_id', $companyId)
                ->where('doctor_id', $newDoctorId)
                ->whereDate('appointment_date', $newDate)
                ->whereTime('appointment_time', $newTime)
                ->lockForUpdate()
                ->first();

            // 3) If target exists and is blocking => reject
            if ($to && in_array($to->status, $blockedStatuses, true)) {
                return response()->json([
                    'msg' => 'Time slot already booked',
                    'status' => 422,
                    'errors' => [
                        'appointment_time' => ['This time slot is already booked for this doctor.'],
                    ],
                ], 422);
            }

            // Save "old" details for log
            $old = [
                'old_doctor_id' => (int) $from->doctor_id,
                'old_date'      => Carbon::parse($from->appointment_date)->toDateString(),
                'old_time'      => substr((string) $from->appointment_time, 0, 5),
            ];

            // If doctor changes, derive doctor_name from new doctor (avoid stale name)
            $newDoctorName = ($from->doctor_id == $newDoctorId)
                ? $from->doctor_name
                : ($doctor->name ?? 'Doctor');

            // 4) CASE A: target exists and is cancelled => reuse target row + cancel old row
            if ($to && $to->status === 'cancelled') {

                $to->update([
                    'patient_id'       => $from->patient_id,
                    'doctor_id'        => $newDoctorId,
                    'doctor_name'      => $newDoctorName,
                    'appointment_date' => $newDate,
                    'appointment_time' => $newTime,
                    'status'           => 'scheduled',
                    'notes'            => $from->notes,
                    'created_by'       => $request->user()->id,
                ]);

                $from->update([
                    'status' => 'cancelled',
                ]);

                ActivityLogger::log(
                    $companyId,
                    $request->user(),
                    'appointment.rescheduled',
                    Appointment::class,
                    $to->id,
                    array_merge($old, [
                        'new_doctor_id'       => $newDoctorId,
                        'new_date'            => $newDate,
                        'new_time'            => $newTime,
                        'patient_id'          => $to->patient_id,
                        'from_appointment_id' => $from->id,
                        'to_appointment_id'   => $to->id,
                    ])
                );

                return response()->json([
                    'msg' => 'Appointment rescheduled',
                    'status' => 200,
                    'data' => $to->fresh()->load([
                        'patient:id,name,email,company_id',
                        'doctor:id,name,company_id,work_start,work_end,slot_minutes',
                    ]),
                ], 200);
            }

            // 5) CASE B: target does not exist => update current row in place
            try {
                $from->update([
                    'doctor_id'        => $newDoctorId,
                    'doctor_name'      => $newDoctorName,
                    'appointment_date' => $newDate,
                    'appointment_time' => $newTime,
                ]);
            } catch (QueryException $e) {
                if ((string) $e->getCode() === '23000') {
                    return response()->json([
                        'msg' => 'Time slot already booked',
                        'status' => 422,
                        'errors' => [
                            'appointment_time' => ['This time slot is already booked for this doctor.'],
                        ],
                    ], 422);
                }
                throw $e;
            }

            ActivityLogger::log(
                $companyId,
                $request->user(),
                'appointment.rescheduled',
                Appointment::class,
                $from->id,
                array_merge($old, [
                    'new_doctor_id'       => $newDoctorId,
                    'new_date'            => $newDate,
                    'new_time'            => $newTime,
                    'patient_id'          => $from->patient_id,
                    'from_appointment_id' => $from->id,
                    'to_appointment_id'   => $from->id,
                ])
            );

            return response()->json([
                'msg' => 'Appointment rescheduled',
                'status' => 200,
                'data' => $from->fresh()->load([
                    'patient:id,name,email,company_id',
                    'doctor:id,name,company_id,work_start,work_end,slot_minutes',
                ]),
            ], 200);
        });
    }
}
