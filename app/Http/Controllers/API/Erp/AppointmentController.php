<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CustomerLedgerEntry;
use App\Models\DentalRecord;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use App\Services\ActivityLogger;
use App\Services\InvoiceNumberService;
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
                'invoice:id,appointment_id,treatment_plan_id,number,status,total',
            ])
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time');

        /*
    |--------------------------------------------------------------------------
    | Search (Scoped + Safe)
    |--------------------------------------------------------------------------
    */
        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('doctor_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('appointment_type', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereDate('appointment_date', $search)
                    ->orWhereTime('appointment_time', $search)
                    ->orWhereHas('patient', function ($p) use ($search) {
                        $p->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = (int) ($request->get('per_page', 20));
        $data = $query->paginate($perPage);

        /*
    |--------------------------------------------------------------------------
    | Transform Response (Frontend Friendly)
    |--------------------------------------------------------------------------
    */
        $rows = collect($data->items())->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'company_id' => $appointment->company_id,

                'patient_id' => $appointment->patient_id,
                'doctor_id' => $appointment->doctor_id,

                'doctor_name' => $appointment->doctor_name,

                'appointment_date' => $appointment->appointment_date,
                'appointment_time' => $appointment->appointment_time,

                'appointment_type' => $appointment->appointment_type,
                'status' => $appointment->status,

                'notes' => $appointment->notes,

                // ✅ clinical data
                'clinical_notes' => $appointment->clinical_notes,
                'diagnosis' => $appointment->diagnosis,
                'next_step' => $appointment->next_step,

                'created_at' => $appointment->created_at,
                'updated_at' => $appointment->updated_at,

                /*
            |--------------------------------------------------------------------------
            | Invoice + Treatment Plan (CRITICAL for frontend)
            |--------------------------------------------------------------------------
            */
                'invoice_id' => $appointment->invoice?->id,
                'invoice_number' => $appointment->invoice?->number,
                'invoice_status' => $appointment->invoice?->status,
                'invoice_total' => $appointment->invoice?->total,

                'treatment_plan_id' => $appointment->invoice?->treatment_plan_id,

                /*
            |--------------------------------------------------------------------------
            | Relations
            |--------------------------------------------------------------------------
            */
                'patient' => $appointment->patient,
                'doctor' => $appointment->doctor,
            ];
        })->values();

        return response()->json([
            'msg' => 'Appointments list',
            'status' => 200,
            'data' => $rows,
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ]);
    }

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
                'invoice:id,number,appointment_id,treatment_plan_id,status,total'
            ])
            ->findOrFail($id);

        // extract linked treatment plan item if exists
        $planItem = \App\Models\TreatmentPlanItem::query()
            ->where('company_id', $companyId)
            ->where('appointment_id', $appointment->id)
            ->first();

        return response()->json([
            'msg' => 'Appointment details',
            'status' => 200,
            'data' => [
                'id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'doctor_id' => $appointment->doctor_id,
                'doctor_name' => $appointment->doctor_name,
                'appointment_date' => $appointment->appointment_date,
                'appointment_time' => $appointment->appointment_time,
                'appointment_type' => $appointment->appointment_type,
                'status' => $appointment->status,
                'notes' => $appointment->notes,
                'clinical_notes' => $appointment->clinical_notes,
                'diagnosis'      => $appointment->diagnosis,
                'next_step'      => $appointment->next_step,
                'created_at' => $appointment->created_at,
                'updated_at' => $appointment->updated_at,

                'patient' => $appointment->patient,
                'doctor' => $appointment->doctor,

                // NEW IMPORTANT DATA
                'invoice_id' => $appointment->invoice?->id,
                'invoice_number' => $appointment->invoice?->number,
                'invoice_status' => $appointment->invoice?->status,
                'invoice_total' => $appointment->invoice?->total,

                'treatment_plan_id' => $appointment->invoice?->treatment_plan_id,

                'treatment_plan_item_id' => $planItem?->id,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $v = Validator::make($request->all(), [
            'notes'  => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['scheduled', 'cancelled', 'no_show'])],

            'clinical_notes' => ['nullable', 'string'],
            'diagnosis'      => ['nullable', 'string'],
            'next_step'      => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'msg' => 'Validation required',
                'status' => 422,
                'errors' => $v->errors(),
            ], 422);
        }

        $data = $v->validated();

        /*
    |--------------------------------------------------------------------------
    | Rule: completed appointment
    |--------------------------------------------------------------------------
    | - allow editing clinical content only
    | - block operational status changes after completion
    */
        if ($appointment->status === 'completed' && array_key_exists('status', $data)) {
            return response()->json([
                'msg' => 'Completed appointments status cannot be changed.',
                'status' => 422,
                'errors' => [
                    'status' => ['Completed appointments status cannot be changed.'],
                ],
            ], 422);
        }

        $track = ['notes', 'clinical_notes', 'diagnosis', 'next_step', 'status'];
        $before = $appointment->only($track);

        $updateData = [
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $appointment->notes,
            'clinical_notes' => array_key_exists('clinical_notes', $data) ? $data['clinical_notes'] : $appointment->clinical_notes,
            'diagnosis' => array_key_exists('diagnosis', $data) ? $data['diagnosis'] : $appointment->diagnosis,
            'next_step' => array_key_exists('next_step', $data) ? $data['next_step'] : $appointment->next_step,
        ];

        // status only if appointment is not completed
        if ($appointment->status !== 'completed' && array_key_exists('status', $data)) {
            $updateData['status'] = $data['status'];
        }

        try {
            $appointment->update($updateData);
        } catch (QueryException $e) {
            throw $e;
        }

        $appointment->refresh();
        $after = $appointment->only($track);

        $changedFields = [];
        foreach ($track as $k) {
            if ((string) ($before[$k] ?? '') !== (string) ($after[$k] ?? '')) {
                $changedFields[] = $k;
            }
        }

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
                'clinical_notes' => $appointment->clinical_notes,
                'diagnosis'      => $appointment->diagnosis,
                'next_step'      => $appointment->next_step,
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

    public function cancel(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        if ($appointment->status === 'cancelled') {
            return response()->json([
                'msg' => 'Appointment already cancelled',
                'status' => 409,
            ], 409);
        }

        if ($appointment->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'status' => ['Only scheduled appointments can be cancelled.'],
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

    public function noShow(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        if ($appointment->status === 'no_show') {
            return response()->json([
                'msg' => 'Appointment already marked as no-show',
                'status' => 409,
            ], 409);
        }

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
        $newTime     = $data['appointment_time'];
        $newDoctorId = (int) $data['doctor_id'];

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

            $fromDate = Carbon::parse($from->appointment_date)->toDateString();
            $fromTime = $from->appointment_time
                ? substr((string) $from->appointment_time, 0, 5)
                : null;

            if (
                (int) $from->doctor_id === (int) $newDoctorId &&
                $fromDate === $newDate &&
                $fromTime === $newTime
            ) {
                return response()->json([
                    'msg' => 'Appointment already in the requested slot',
                    'status' => 200,
                    'data' => $from->fresh()->load([
                        'patient:id,name,email,company_id',
                        'doctor:id,name,company_id,work_start,work_end,slot_minutes',
                    ]),
                ], 200);
            }

            $to = Appointment::query()
                ->where('company_id', $companyId)
                ->where('doctor_id', $newDoctorId)
                ->whereDate('appointment_date', $newDate)
                ->whereTime('appointment_time', $newTime)
                ->lockForUpdate()
                ->first();

            if ($to && in_array($to->status, $blockedStatuses, true)) {
                return response()->json([
                    'msg' => 'Time slot already booked',
                    'status' => 422,
                    'errors' => [
                        'appointment_time' => ['This time slot is already booked for this doctor.'],
                    ],
                ], 422);
            }

            $old = [
                'old_status'    => $from->status,
                'old_doctor_id' => (int) $from->doctor_id,
                'old_date'      => Carbon::parse($from->appointment_date)->toDateString(),
                'old_time'      => substr((string) $from->appointment_time, 0, 5),
            ];

            $newDoctorName = ((int) $from->doctor_id === (int) $newDoctorId)
                ? $from->doctor_name
                : ($doctor->name ?? 'Doctor');

            // CASE A: target slot موجود لكنه cancelled → نعيد استخدامه
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
                        'new_status'          => 'scheduled',
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

            // CASE B: target slot غير موجود → نعدل نفس الصف
            try {
                $from->update([
                    'doctor_id'        => $newDoctorId,
                    'doctor_name'      => $newDoctorName,
                    'appointment_date' => $newDate,
                    'appointment_time' => $newTime,
                    'status'           => 'scheduled',
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
                    'new_status'          => 'scheduled',
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
    ////////////////////////////////////////////////////////////////////////////////////
    //book method for dental clinic only with consultaion fee
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
                ->findOrFail((int) $data['doctor_id']);
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
        $startTime = $doctor->work_start ?? '09:00';
        $endTime = $doctor->work_end ?? '17:00';
        $slotMinutes = (int) ($doctor->slot_minutes ?? 30);

        $start = Carbon::parse("$date $startTime");
        $end = Carbon::parse("$date $endTime");
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

        $doctorName = trim((string) ($data['doctor_name'] ?? '')) ?: ($doctor->name ?? 'Doctor');
        $blockedStatuses = ['scheduled', 'completed', 'no_show'];

        return DB::transaction(function () use (
            $request,
            $companyId,
            $data,
            $date,
            $time,
            $doctorId,
            $doctorName,
            $blockedStatuses
        ) {
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
                        'appointment_type' => 'consultation',
                    ]);

                    // Auto-create consultation invoice for rebooked cancelled slot if not exists
                    $existingInvoice = \App\Models\Invoice::query()
                        ->where('company_id', $companyId)
                        ->where('appointment_id', $existing->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$existingInvoice) {
                        $consultationProduct = \App\Models\Product::query()
                            ->where('company_id', $companyId)
                            ->where('title_en', 'Consultation')
                            ->first();

                        if ($consultationProduct) {
                            $consultationTotal = (float) ($consultationProduct->unit_price ?? 0);

                            if ($consultationTotal > 0) {
                                $order = \App\Models\Order::create([
                                    'company_id'  => $companyId,
                                    'customer_id' => $existing->patient_id,
                                    'title_en'    => 'Consultation Visit',
                                    'title_ar'    => 'رسوم كشف',
                                    'status'      => 'confirmed',
                                    'total'       => $consultationTotal,
                                    'created_by'  => $request->user()->id,
                                ]);

                                \App\Models\OrderItem::create([
                                    'company_id' => $companyId,
                                    'order_id'   => $order->id,
                                    'product_id' => $consultationProduct->id,
                                    'quantity'   => 1,
                                    'unit_price' => $consultationTotal,
                                    'total'      => $consultationTotal,
                                ]);

                                $number = \App\Services\InvoiceNumberService::generate($companyId);

                                $invoice = \App\Models\Invoice::create([
                                    'company_id'        => $companyId,
                                    'number'            => $number,
                                    'order_id'          => $order->id,
                                    'appointment_id'    => $existing->id,
                                    'treatment_plan_id' => null,
                                    'customer_id'       => $existing->patient_id,
                                    'total'             => $consultationTotal,
                                    'status'            => 'unpaid',
                                    'issued_at'         => now(),
                                ]);

                                \App\Models\InvoiceItem::create([
                                    'company_id' => $companyId,
                                    'invoice_id' => $invoice->id,
                                    'product_id' => $consultationProduct->id,
                                    'quantity'   => 1,
                                    'unit_price' => $consultationTotal,
                                    'total'      => $consultationTotal,
                                ]);

                                CustomerLedgerEntry::create([
                                    'company_id'  => $companyId,
                                    'customer_id' => $invoice->customer_id,
                                    'invoice_id'  => $invoice->id,
                                    'payment_id'  => null,
                                    'refund_id'   => null,
                                    'type'        => 'invoice',
                                    'debit'       => $invoice->total,
                                    'credit'      => 0,
                                    'entry_date'  => $invoice->issued_at ?? now(),
                                    'description' => 'Consultation invoice #' . $invoice->number,
                                ]);

                                // ✅ Auto-apply available customer credit
                                $this->autoApplyCustomerCredit($invoice, $request->user());
                            }
                        }
                    }

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
                    'doctor_id' => $doctorId,
                    'doctor_name' => $doctorName,
                    'appointment_date' => $date,
                    'appointment_time' => $time,
                    'appointment_type' => 'consultation',
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

            // Auto-create consultation invoice for newly booked appointment
            $existingInvoice = \App\Models\Invoice::query()
                ->where('company_id', $companyId)
                ->where('appointment_id', $appointment->id)
                ->lockForUpdate()
                ->first();

            if (!$existingInvoice) {
                $consultationProduct = \App\Models\Product::query()
                    ->where('company_id', $companyId)
                    ->where('title_en', 'Consultation')
                    ->first();

                if ($consultationProduct) {
                    $consultationTotal = (float) ($consultationProduct->unit_price ?? 0);

                    if ($consultationTotal > 0) {
                        $order = \App\Models\Order::create([
                            'company_id'  => $companyId,
                            'customer_id' => $appointment->patient_id,
                            'title_en'    => 'Consultation Visit',
                            'title_ar'    => 'رسوم كشف',
                            'status'      => 'confirmed',
                            'total'       => $consultationTotal,
                            'created_by'  => $request->user()->id,
                        ]);

                        \App\Models\OrderItem::create([
                            'company_id' => $companyId,
                            'order_id'   => $order->id,
                            'product_id' => $consultationProduct->id,
                            'quantity'   => 1,
                            'unit_price' => $consultationTotal,
                            'total'      => $consultationTotal,
                        ]);

                        $number = \App\Services\InvoiceNumberService::generate($companyId);

                        $invoice = \App\Models\Invoice::create([
                            'company_id'        => $companyId,
                            'number'            => $number,
                            'order_id'          => $order->id,
                            'appointment_id'    => $appointment->id,
                            'treatment_plan_id' => null,
                            'customer_id'       => $appointment->patient_id,
                            'total'             => $consultationTotal,
                            'status'            => 'unpaid',
                            'issued_at'         => now(),
                        ]);

                        \App\Models\InvoiceItem::create([
                            'company_id' => $companyId,
                            'invoice_id' => $invoice->id,
                            'product_id' => $consultationProduct->id,
                            'quantity'   => 1,
                            'unit_price' => $consultationTotal,
                            'total'      => $consultationTotal,
                        ]);

                        CustomerLedgerEntry::create([
                            'company_id'  => $companyId,
                            'customer_id' => $invoice->customer_id,
                            'invoice_id'  => $invoice->id,
                            'payment_id'  => null,
                            'refund_id'   => null,
                            'type'        => 'invoice',
                            'debit'       => $invoice->total,
                            'credit'      => 0,
                            'entry_date'  => $invoice->issued_at ?? now(),
                            'description' => 'Consultation invoice #' . $invoice->number,
                        ]);

                        // ✅ Auto-apply available customer credit
                        $this->autoApplyCustomerCredit($invoice, $request->user());
                    }
                }
            }

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

    //book method for dental clinic only with start procedure
    public function complete(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $data = $request->validate([
            'doctor_name' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],

            'clinical_notes' => ['nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'next_step' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($request, $companyId, $data, $appointment) {
            $appointment = Appointment::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            if ($appointment->status === 'completed') {
                return response()->json([
                    'msg' => 'Appointment already completed',
                    'status' => 409,
                ], 409);
            }

            if (!in_array($appointment->status, ['scheduled'], true)) {
                return response()->json([
                    'msg' => 'Only scheduled appointments can be completed',
                    'status' => 422,
                    'errors' => [
                        'status' => ['Cancelled or no-show appointments cannot be completed directly.'],
                    ],
                ], 422);
            }

            $appointmentType = (string) ($appointment->appointment_type ?? 'consultation');

            $appointment->update([
                'doctor_name' => $data['doctor_name'] ?? $appointment->doctor_name,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $appointment->notes,
                'clinical_notes' => array_key_exists('clinical_notes', $data) ? $data['clinical_notes'] : $appointment->clinical_notes,
                'diagnosis' => array_key_exists('diagnosis', $data) ? $data['diagnosis'] : $appointment->diagnosis,
                'next_step' => array_key_exists('next_step', $data) ? $data['next_step'] : $appointment->next_step,
            ]);

            /*
        |--------------------------------------------------------------------------
        | 1) Consultation Appointment
        |--------------------------------------------------------------------------
        | - invoice is created earlier during booking
        | - complete should NOT create another invoice
        | - it should only close the appointment and return existing invoice_id
        */
            if ($appointmentType === 'consultation') {
                $existingConsultationInvoice = Invoice::query()
                    ->where('company_id', $companyId)
                    ->where('appointment_id', $appointment->id)
                    ->lockForUpdate()
                    ->first();

                if (!$existingConsultationInvoice) {
                    return response()->json([
                        'msg' => 'Consultation invoice not found for this appointment',
                        'status' => 422,
                        'errors' => [
                            'appointment' => ['This consultation appointment has no linked invoice.'],
                        ],
                    ], 422);
                }

                $appointment->update([
                    'status' => 'completed',
                ]);

                ActivityLogger::log(
                    $companyId,
                    $request->user(),
                    'appointment.completed',
                    Appointment::class,
                    $appointment->id,
                    [
                        'appointment_type' => 'consultation',
                        'invoice_id' => $existingConsultationInvoice->id,
                        'invoice_number' => $existingConsultationInvoice->number,
                        'treatment_plan_id' => $existingConsultationInvoice->treatment_plan_id,
                        'total' => (float) $existingConsultationInvoice->total,
                        'invoice_status' => $existingConsultationInvoice->status,
                    ]
                );

                return response()->json([
                    'msg' => 'Consultation appointment completed successfully',
                    'status' => 200,
                    'invoice_id' => $existingConsultationInvoice->id,
                    'order_id' => $existingConsultationInvoice->order_id,
                    'invoice_number' => $existingConsultationInvoice->number,
                    'treatment_plan_id' => $existingConsultationInvoice->treatment_plan_id,
                    'invoice_status' => $existingConsultationInvoice->status,
                    'total' => (float) $existingConsultationInvoice->total,
                ], 200);
            }

            /*
        |--------------------------------------------------------------------------
        | 2) Treatment Appointment
        |--------------------------------------------------------------------------
        | - appointment must come from Start Procedure
        | - it must be linked to one treatment_plan_item
        | - complete creates invoice for THIS item only
        | - then auto-apply any available customer credit
        | - then create dental record automatically
        */
            if ($appointmentType === 'treatment') {
                $linkedPlanItem = \App\Models\TreatmentPlanItem::query()
                    ->where('company_id', $companyId)
                    ->where('appointment_id', $appointment->id)
                    ->lockForUpdate()
                    ->first();

                if (!$linkedPlanItem) {
                    return response()->json([
                        'msg' => 'Treatment appointment is not linked to a treatment plan item',
                        'status' => 422,
                        'errors' => [
                            'appointment' => ['This treatment appointment must be started from a treatment plan item.'],
                        ],
                    ], 422);
                }

                if ($linkedPlanItem->status === 'completed') {
                    return response()->json([
                        'msg' => 'This treatment procedure is already completed',
                        'status' => 409,
                    ], 409);
                }

                $plan = TreatmentPlan::query()
                    ->where('company_id', $companyId)
                    ->findOrFail($linkedPlanItem->treatment_plan_id);

                if ((int) $plan->customer_id !== (int) $appointment->patient_id) {
                    return response()->json([
                        'msg' => 'Linked treatment plan does not belong to this customer',
                        'status' => 422,
                        'errors' => [
                            'appointment' => ['Treatment plan customer mismatch.'],
                        ],
                    ], 422);
                }

                $existingTreatmentInvoice = Invoice::query()
                    ->where('company_id', $companyId)
                    ->where('appointment_id', $appointment->id)
                    ->whereHas('order', function ($q) {
                        $q->where('title_en', 'Appointment Services');
                    })
                    ->lockForUpdate()
                    ->first();

                if ($existingTreatmentInvoice) {
                    return response()->json([
                        'msg' => 'Treatment invoice already exists for this appointment',
                        'status' => 409,
                        'invoice_id' => $existingTreatmentInvoice->id,
                        'order_id' => $existingTreatmentInvoice->order_id,
                        'invoice_number' => $existingTreatmentInvoice->number,
                        'treatment_plan_id' => $existingTreatmentInvoice->treatment_plan_id,
                        'treatment_plan_item_id' => $linkedPlanItem->id,
                        'invoice_status' => $existingTreatmentInvoice->status,
                        'total' => (float) $existingTreatmentInvoice->total,
                    ], 409);
                }

                $price = (float) $linkedPlanItem->price;

                if ($price <= 0) {
                    return response()->json([
                        'msg' => 'Invalid treatment item price',
                        'status' => 422,
                        'errors' => [
                            'appointment' => ['The linked treatment item must have a valid price.'],
                        ],
                    ], 422);
                }

                $treatmentServiceProduct = \App\Models\Product::query()
                    ->where('company_id', $companyId)
                    ->where('title_en', 'Appointment Service')
                    ->first();

                if (!$treatmentServiceProduct) {
                    return response()->json([
                        'msg' => 'Missing service product (Appointment Service). Create it first.',
                        'status' => 422,
                        'errors' => [
                            'product' => ['Appointment Service product is required for treatment invoicing.'],
                        ],
                    ], 422);
                }

                $order = \App\Models\Order::create([
                    'company_id' => $companyId,
                    'customer_id' => $appointment->patient_id,
                    'title_en' => 'Appointment Services',
                    'title_ar' => 'خدمات الموعد',
                    'status' => 'confirmed',
                    'total' => $price,
                    'created_by' => $request->user()->id,
                ]);

                \App\Models\OrderItem::create([
                    'company_id' => $companyId,
                    'order_id' => $order->id,
                    'product_id' => $treatmentServiceProduct->id,
                    'quantity' => 1,
                    'unit_price' => $price,
                    'total' => $price,
                ]);

                $number = InvoiceNumberService::generate($companyId);

                $invoice = \App\Models\Invoice::create([
                    'company_id' => $companyId,
                    'number' => $number,
                    'order_id' => $order->id,
                    'appointment_id' => $appointment->id,
                    'treatment_plan_id' => $plan->id,
                    'customer_id' => $appointment->patient_id,
                    'total' => $price,
                    'status' => 'unpaid',
                    'issued_at' => now(),
                ]);

                \App\Models\InvoiceItem::create([
                    'company_id' => $companyId,
                    'invoice_id' => $invoice->id,
                    'product_id' => $treatmentServiceProduct->id,
                    'quantity' => 1,
                    'unit_price' => $price,
                    'total' => $price,
                ]);

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

                // Auto-apply available customer credit
                $this->autoApplyCustomerCredit($invoice, $request->user());
                $invoice->refresh();

                $appointment->update([
                    'status' => 'completed',
                ]);

                $currentCompleted = (int) ($linkedPlanItem->completed_sessions ?? 0);
                $plannedSessions = max((int) ($linkedPlanItem->planned_sessions ?? 1), 1);

                $newCompleted = min($currentCompleted + 1, $plannedSessions);
                $remainingAfterComplete = max($plannedSessions - $newCompleted, 0);

                $linkedPlanItem->update([
                    'completed_sessions' => $newCompleted,
                    'status' => $remainingAfterComplete > 0 ? 'planned' : 'completed',
                    'appointment_id' => null,
                    'completed_at' => $remainingAfterComplete === 0 ? now() : null,
                ]);

                DentalRecord::create([
                    'company_id' => $companyId,
                    'customer_id' => $appointment->patient_id,
                    'appointment_id' => $appointment->id,
                    'doctor_id' => $appointment->doctor_id,
                    'procedure_id' => $linkedPlanItem->procedure_id,
                    'tooth_number' => $linkedPlanItem->tooth_number,
                    'surface' => $linkedPlanItem->surface,
                    'status' => 'completed',
                    'notes' => $data['clinical_notes'] ?? $data['notes'] ?? $linkedPlanItem->notes,
                    'treatment_plan_item_id' => $linkedPlanItem->id,
                ]);

                ActivityLogger::log(
                    $companyId,
                    $request->user(),
                    'appointment.completed',
                    Appointment::class,
                    $appointment->id,
                    [
                        'appointment_type' => 'treatment',
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->number,
                        'treatment_plan_id' => $invoice->treatment_plan_id,
                        'treatment_plan_item_id' => $linkedPlanItem->id,
                        'total' => (float) $invoice->total,
                        'invoice_status' => $invoice->status,
                    ]
                );

                ActivityLogger::log(
                    $companyId,
                    $request->user(),
                    'treatment_plan_item.session_completed',
                    \App\Models\TreatmentPlanItem::class,
                    $linkedPlanItem->id,
                    [
                        'appointment_id' => $appointment->id,
                        'invoice_id' => $invoice->id,
                        'treatment_plan_id' => $plan->id,
                        'completed_sessions' => $newCompleted,
                        'planned_sessions' => $plannedSessions,
                        'remaining_sessions' => $remainingAfterComplete,
                        'item_status' => $remainingAfterComplete > 0 ? 'planned' : 'completed',
                    ]
                );

                return response()->json([
                    'msg' => 'Treatment appointment completed and invoice created',
                    'status' => 200,
                    'invoice_id' => $invoice->id,
                    'order_id' => $order->id,
                    'invoice_number' => $invoice->number,
                    'treatment_plan_id' => $invoice->treatment_plan_id,
                    'treatment_plan_item_id' => $linkedPlanItem->id,
                    'invoice_status' => $invoice->status,
                    'total' => (float) $invoice->total,
                    'completed_sessions' => $newCompleted,
                    'planned_sessions' => $plannedSessions,
                    'remaining_sessions' => $remainingAfterComplete,
                    'item_status' => $remainingAfterComplete > 0 ? 'planned' : 'completed',
                ], 200);
            }

            return response()->json([
                'msg' => 'Invalid appointment type',
                'status' => 422,
                'errors' => [
                    'appointment_type' => ['Unsupported appointment type.'],
                ],
            ], 422);
        });
    }

    private function autoApplyCustomerCredit(Invoice $invoice, $user): void
    {
        $companyId = $invoice->company_id;

        $totalCustomerCredit = DB::table('customer_credits')
            ->where('company_id', $companyId)
            ->where('customer_id', $invoice->customer_id)
            ->where('type', 'credit')
            ->sum('amount');

        $totalCustomerDebit = DB::table('customer_credits')
            ->where('company_id', $companyId)
            ->where('customer_id', $invoice->customer_id)
            ->where('type', 'debit')
            ->sum('amount');

        $availableCredit = max(0, (float) $totalCustomerCredit - (float) $totalCustomerDebit);

        if ($availableCredit <= 0) {
            return;
        }

        $totalApplied = Payment::where('company_id', $companyId)
            ->where('invoice_id', $invoice->id)
            ->sum('applied_amount');

        $totalRefunded = DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->where('payments.company_id', $companyId)
            ->where('payments.invoice_id', $invoice->id)
            ->where('payment_refunds.company_id', $companyId)
            ->where('payment_refunds.applies_to', 'invoice')
            ->sum('payment_refunds.amount');

        $totalCreditApplied = DB::table('customer_credits')
            ->where('company_id', $companyId)
            ->where('invoice_id', $invoice->id)
            ->where('type', 'debit')
            ->sum('amount');

        $netPaid = (float) $totalApplied - (float) $totalRefunded + (float) $totalCreditApplied;
        $remaining = max(0, (float) $invoice->total - (float) $netPaid);

        if ($remaining <= 0) {
            return;
        }

        $creditToApply = min($availableCredit, $remaining);

        if ($creditToApply <= 0) {
            return;
        }

        DB::table('customer_credits')->insert([
            'company_id'  => $companyId,
            'customer_id' => $invoice->customer_id,
            'invoice_id'  => $invoice->id,
            'payment_id'  => null,
            'type'        => 'debit',
            'amount'      => $creditToApply,
            'entry_date'  => now(),
            'description' => 'Customer credit auto-applied to invoice #' . $invoice->number,
            'created_by'  => $user->id ?? null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        CustomerLedgerEntry::create([
            'company_id'  => $companyId,
            'customer_id' => $invoice->customer_id,
            'invoice_id'  => $invoice->id,
            'payment_id'  => null,
            'refund_id'   => null,
            'type'        => 'credit_apply',
            'debit'       => 0,
            'credit'      => $creditToApply,
            'entry_date'  => now(),
            'description' => 'Customer credit auto-applied to invoice #' . $invoice->number,
        ]);

        $arAccount = \App\Models\Account::where('company_id', $companyId)
            ->where('code', '1100')
            ->first();

        $creditAccount = \App\Models\Account::where('company_id', $companyId)
            ->where('code', '2100')
            ->first();

        if ($arAccount && $creditAccount) {
            \App\Services\AccountingService::createEntry(
                $invoice,
                'Customer credit auto-applied to invoice #' . $invoice->number,
                [
                    [
                        'account_id' => $creditAccount->id,
                        'debit' => $creditToApply,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $arAccount->id,
                        'debit' => 0,
                        'credit' => $creditToApply,
                    ],
                ],
                $user->id ?? null,
                now()->toDateString()
            );
        }

        $netAfter = $netPaid + $creditToApply;

        if ($netAfter <= 0) {
            $status = 'unpaid';
        } elseif ($netAfter < (float) $invoice->total) {
            $status = 'partially_paid';
        } else {
            $status = 'paid';
        }

        $invoice->update([
            'status' => $status,
        ]);
    }
}
