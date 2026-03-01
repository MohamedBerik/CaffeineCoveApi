<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CustomerLedgerEntry;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\TreatmentPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Carbon\Carbon;
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
                'doctor:id,name,company_id,work_start,work_end,slot_minutes'
            ])
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time');

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
     * ✅ required doctor_id + tenant scoped patient/doctor + collision check by doctor_id + whereTime
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
            'doctor_name' => ['nullable', 'string', 'max:190'], // optional display override
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

        $doctor = Doctor::where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail((int) $data['doctor_id']);

        // ✅ prevent duplicate slot (doctor_id + date + time) + whereTime solves 10:00 vs 10:00:00
        $slotTaken = Appointment::query()
            ->where('company_id', $companyId)
            ->where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $date)
            ->whereTime('appointment_time', $time)
            ->whereIn('status', ['scheduled', 'completed', 'no_show'])
            ->exists();

        if ($slotTaken) {
            return response()->json([
                'msg' => 'Time slot already booked',
                'status' => 422,
                'errors' => [
                    'appointment_time' => ['This time slot is already booked for this doctor.'],
                ],
            ], 422);
        }

        $doctorName = trim((string)($data['doctor_name'] ?? '')) ?: ($doctor->name ?? 'Doctor');

        try {
            $appointment = Appointment::create([
                'company_id' => $companyId,
                'patient_id' => $data['patient_id'],
                'doctor_id'  => $doctor->id,
                'doctor_name' => $doctorName,
                'appointment_date' => $date,
                'appointment_time' => $time,
                'status' => $data['status'] ?? 'scheduled',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
        } catch (QueryException $e) {
            // fallback for race conditions
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

        return response()->json([
            'msg' => 'Appointment created',
            'status' => 201,
            'data' => $appointment->load([
                'patient:id,name,email,company_id',
                'doctor:id,name,company_id,work_start,work_end,slot_minutes',
            ]),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->with([
                'patient:id,name,email,company_id',
                'doctor:id,name,company_id,work_start,work_end,slot_minutes'
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
     */
    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $v = Validator::make($request->all(), [
            'patient_id' => [
                'sometimes',
                'integer',
                Rule::exists('customers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'doctor_id' => [
                'sometimes',
                'integer',
                Rule::exists('doctors', 'id')->where(fn($q) => $q->where('company_id', $companyId)->where('is_active', true)),
            ],
            'doctor_name' => ['nullable', 'string', 'max:190'],
            'appointment_date' => ['sometimes', 'date'],
            'appointment_time' => ['sometimes', 'date_format:H:i'],
            'status' => ['sometimes', Rule::in(['scheduled', 'completed', 'cancelled', 'no_show'])],
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

        $newDoctorId = (int) ($data['doctor_id'] ?? $appointment->doctor_id);

        $newDate = Carbon::parse($data['appointment_date'] ?? $appointment->appointment_date)->toDateString();

        // appointment_time in DB might be TIME (10:00:00), normalize to H:i
        $currentTime = $appointment->appointment_time
            ? Carbon::parse($appointment->appointment_time)->format('H:i')
            : null;

        $newTime = $data['appointment_time'] ?? $currentTime;

        // if any key field missing (shouldn't), keep safe
        if (!$newTime) {
            return response()->json([
                'msg' => 'Invalid appointment_time',
                'status' => 422,
                'errors' => [
                    'appointment_time' => ['appointment_time is required.'],
                ],
            ], 422);
        }

        $slotTaken = Appointment::query()
            ->where('company_id', $companyId)
            ->where('doctor_id', $newDoctorId)
            ->whereDate('appointment_date', $newDate)
            ->whereTime('appointment_time', $newTime)
            ->whereIn('status', ['scheduled', 'completed', 'no_show'])
            ->where('id', '!=', $appointment->id)
            ->exists();

        if ($slotTaken) {
            return response()->json([
                'msg' => 'Time slot already booked',
                'status' => 422,
                'errors' => [
                    'appointment_time' => ['This time slot is already booked for this doctor.'],
                ],
            ], 422);
        }

        // ✅ If doctor changed OR doctor_name empty, derive from doctor
        if (array_key_exists('doctor_id', $data) || empty($data['doctor_name'])) {
            $doctor = Doctor::where('company_id', $companyId)
                ->where('is_active', true)
                ->findOrFail($newDoctorId);

            $data['doctor_name'] = trim((string)($data['doctor_name'] ?? '')) ?: ($doctor->name ?? 'Doctor');
        }

        // normalize date/time formats on update if provided
        if (isset($data['appointment_date'])) {
            $data['appointment_date'] = Carbon::parse($data['appointment_date'])->toDateString();
        }
        if (isset($data['appointment_time'])) {
            // keep H:i
            $data['appointment_time'] = $newTime;
        }

        try {
            $appointment->update($data);
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

        return response()->json([
            'msg' => 'Appointment updated',
            'status' => 200,
            'data' => $appointment->fresh()->load([
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
     * ✅ doctor_id optional: if missing choose first active doctor in company
     * ✅ collision check uses doctor_id + whereTime
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

        // Resolve doctor
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

        // Doctor working settings
        $startTime   = $doctor->work_start ?? '09:00';
        $endTime     = $doctor->work_end ?? '17:00';
        $slotMinutes = (int) ($doctor->slot_minutes ?? 30);

        $start = Carbon::parse("$date $startTime");
        $end   = Carbon::parse("$date $endTime");
        $requested = Carbon::parse("$date $time");

        if ($requested->lt($start) || $requested->gte($end)) {
            throw ValidationException::withMessages([
                'appointment_time' => ['Time is outside working hours.']
            ]);
        }

        $diff = $start->diffInMinutes($requested);
        if ($slotMinutes <= 0 || ($diff % $slotMinutes !== 0)) {
            throw ValidationException::withMessages([
                'appointment_time' => ['Time must match slot interval.']
            ]);
        }

        $exists = Appointment::query()
            ->where('company_id', $companyId)
            ->where('doctor_id', $doctorId)
            ->whereDate('appointment_date', $date)
            ->whereTime('appointment_time', $time)
            ->whereIn('status', ['scheduled', 'completed', 'no_show'])
            ->exists();

        if ($exists) {
            return response()->json([
                "msg" => "Time slot already booked",
                "status" => 422,
                "errors" => [
                    "appointment_time" => [
                        "This time slot is already booked for this doctor."
                    ]
                ]
            ], 422);
        }

        $doctorName = trim((string)($data['doctor_name'] ?? '')) ?: ($doctor->name ?? 'Doctor');

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

        return response()->json([
            'msg' => 'Appointment booked',
            'status' => 201,
            'data' => $appointment->load([
                'patient:id,name,email,company_id',
                'doctor:id,name,company_id,work_start,work_end,slot_minutes',
            ]),
        ], 201);
    }

    public function cancel(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::where('company_id', $companyId)
            ->findOrFail($id);

        if ($appointment->status === 'completed') {
            throw ValidationException::withMessages([
                'status' => ['Completed appointments cannot be cancelled.']
            ]);
        }

        $appointment->update([
            'status' => 'cancelled'
        ]);

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
     * ✅ COMPLETE APPOINTMENT + CREATE INVOICE (+ optional treatment_plan_id)
     * ✅ locks appointment, prevents duplicate invoices
     * ✅ validates treatment plan belongs to same customer
     */
    public function complete(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        // fetch appointment first
        $appointment = Appointment::where('company_id', $companyId)->findOrFail($id);

        $data = $request->validate([
            'total' => ['required', 'numeric', 'min:0.01'],
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

            // lock appointment
            $appointment = Appointment::where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            // prevent duplicate invoice inside tx
            $existingInvoice = Invoice::where('company_id', $companyId)
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

            // validate plan belongs to same customer
            $planId = $data['treatment_plan_id'] ?? null;
            if ($planId) {
                $plan = TreatmentPlan::where('company_id', $companyId)->findOrFail($planId);
                if ((int)$plan->customer_id !== (int)$appointment->patient_id) {
                    return response()->json([
                        'msg' => 'Treatment plan does not belong to this customer',
                        'status' => 422,
                        'errors' => [
                            'treatment_plan_id' => ['Treatment plan customer_id mismatch.'],
                        ],
                        'debug' => [
                            'appointment_patient_id' => $appointment->patient_id,
                            'plan_customer_id' => $plan->customer_id,
                        ],
                    ], 422);
                }
            }

            // update appointment before close
            $appointment->update([
                'doctor_name' => $data['doctor_name'] ?? $appointment->doctor_name,
                'notes'       => $data['notes'] ?? $appointment->notes,
            ]);

            // 1) Order
            $order = \App\Models\Order::create([
                'company_id'   => $companyId,
                'customer_id'  => $appointment->patient_id,
                'status'       => 'confirmed',
                'total'        => $data['total'],
                'created_by'   => $request->user()->id,
            ]);

            // 2) OrderItem
            \App\Models\OrderItem::create([
                'company_id'  => $companyId,
                'order_id'    => $order->id,
                'product_id'  => $serviceProduct->id,
                'quantity'    => 1,
                'unit_price'  => $data['total'],
                'total'       => $data['total'],
            ]);

            // 3) invoice number
            $number = 'INV-' . now()->format('YmdHis') . '-' . $order->id;

            // 4) Invoice
            $invoice = \App\Models\Invoice::create([
                'company_id'        => $companyId,
                'number'            => $number,
                'order_id'          => $order->id,
                'appointment_id'    => $appointment->id,
                'treatment_plan_id' => $data['treatment_plan_id'] ?? null,
                'customer_id'       => $appointment->patient_id,
                'total'             => $data['total'],
                'status'            => 'unpaid',
                'issued_at'         => now(),
            ]);

            // 5) InvoiceItem
            \App\Models\InvoiceItem::create([
                'company_id'  => $companyId,
                'invoice_id'  => $invoice->id,
                'product_id'  => $serviceProduct->id,
                'quantity'    => 1,
                'unit_price'  => $data['total'],
                'total'       => $data['total'],
            ]);

            // 5.1) Ledger invoice issued
            $exists = CustomerLedgerEntry::where('company_id', $companyId)
                ->where('invoice_id', $invoice->id)
                ->where('type', 'invoice')
                ->exists();

            if (!$exists) {
                CustomerLedgerEntry::create([
                    'company_id'   => $companyId,
                    'customer_id'  => $invoice->customer_id,
                    'invoice_id'   => $invoice->id,
                    'payment_id'   => null,
                    'refund_id'    => null,
                    'type'         => 'invoice',
                    'debit'        => $invoice->total,
                    'credit'       => 0,
                    'entry_date'   => $invoice->issued_at ?? now(),
                    'description'  => 'Invoice issued #' . $invoice->number,
                ]);
            }

            // 6) close appointment
            $appointment->update([
                'status' => 'completed',
            ]);

            return response()->json([
                'msg' => 'Appointment completed and invoice created',
                'status' => 200,
                'invoice_id' => $invoice->id,
                'order_id' => $order->id,
                'invoice_number' => $invoice->number,
                'treatment_plan_id' => $invoice->treatment_plan_id,
            ]);
        });
    }

    public function noShow(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::where('company_id', $companyId)
            ->findOrFail($id);

        if ($appointment->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'status' => ['Only scheduled appointments can be marked as no-show.']
            ]);
        }

        $appointment->update([
            'status' => 'no_show'
        ]);

        return response()->json([
            'msg' => 'Appointment marked as no-show',
            'status' => 200,
            'data' => $appointment->fresh()->load([
                'patient:id,name,email,company_id',
                'doctor:id,name,company_id,work_start,work_end,slot_minutes',
            ]),
        ]);
    }
}
