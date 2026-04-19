<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CustomerLedgerEntry;
use App\Models\DentalRecord;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TreatmentPlan;
use App\Services\ActivityLogger;
use App\Services\InvoiceNumberService;
use App\Services\Tenant;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Traits\ValidatesAppointments;
use App\Traits\HandlesAppointmentReminders;
use App\Events\DashboardUpdated;
use App\Services\InsightService;
use App\Events\InsightGenerated;

class AppointmentController extends Controller
{
    use ValidatesAppointments, HandlesAppointmentReminders;

    public function index(Request $request)
    {
        $query = Appointment::query()
            ->with([
                'patient:id,name,email,company_id',
                'doctor:id,name,company_id,work_start,work_end,slot_minutes',
                'invoice:id,appointment_id,treatment_plan_id,number,status,total',
            ])
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time');

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
                'clinical_notes' => $appointment->clinical_notes,
                'diagnosis' => $appointment->diagnosis,
                'next_step' => $appointment->next_step,
                'created_at' => $appointment->created_at,
                'updated_at' => $appointment->updated_at,
                'invoice_id' => $appointment->invoice?->id,
                'invoice_number' => $appointment->invoice?->number,
                'invoice_status' => $appointment->invoice?->status,
                'invoice_total' => $appointment->invoice?->total,
                'treatment_plan_id' => $appointment->invoice?->treatment_plan_id,
                'patient' => $appointment->patient,
                'doctor' => $appointment->doctor,
                'reminder_status' => $appointment->reminder_status,
                'last_reminder_at' => $appointment->last_reminder_at,
                'next_reminder_at' => $appointment->next_reminder_at,
                'reminder_sent_count' => (int) ($appointment->reminder_sent_count ?? 0),
                'reminder_stage' => $appointment->reminder_stage,
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
        $companyId = Tenant::id();

        $v = Validator::make($request->all(), [
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id'),
            ],
            'doctor_id' => [
                'required',
                'integer',
                Rule::exists('doctors', 'id')->where('is_active', true),
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
            ->where('is_active', true)
            ->findOrFail((int) $data['doctor_id']);

        $doctorName = trim((string) ($data['doctor_name'] ?? '')) ?: ($doctor->name ?? 'Doctor');

        $requestedStatus = $data['status'] ?? 'scheduled';
        $blockedStatuses = ['scheduled', 'completed', 'no_show'];

        $this->validateAppointmentDateTime($doctor, $date, $time);

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
                        'status' => $requestedStatus,
                        'notes' => $data['notes'] ?? null,
                        'created_by' => $request->user()->id,
                        'appointment_date' => $date,
                        'appointment_time' => $time,
                        'appointment_type' => 'consultation',
                        ...$this->buildPendingReminder($date, $time),
                    ]);

                    ActivityLogger::log(
                        $companyId,
                        $request->user(),
                        'appointment.rebooked',
                        Appointment::class,
                        $existing->id,
                        [
                            'doctor_id'  => $existing->doctor_id,
                            'patient_id' => $existing->patient_id,
                            'date'       => $date,
                            'time'       => substr((string) $time, 0, 5),
                        ]
                    );

                    return response()->json([
                        'msg' => 'Appointment rebooked',
                        'status' => 200,
                        'data' => $existing->fresh(),
                    ]);
                }
            }

            try {
                $appointment = Appointment::create([
                    'company_id' => $companyId,
                    'patient_id' => $data['patient_id'],
                    'doctor_id' => $doctor->id,
                    'doctor_name' => $doctorName,
                    'appointment_date' => $date,
                    'appointment_time' => $time,
                    'status' => $requestedStatus,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id,
                    'appointment_type' => 'consultation',
                    ...$this->buildPendingReminder($date, $time),
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
                'appointment.created',
                Appointment::class,
                $appointment->id,
                [
                    'doctor_id'  => $appointment->doctor_id,
                    'patient_id' => $appointment->patient_id,
                    'date'       => $date,
                    'time'       => substr((string) $time, 0, 5),
                    'status'     => $appointment->status,
                ]
            );

            return response()->json([
                'msg' => 'Appointment created',
                'status' => 201,
                'data' => $appointment,
            ], 201);
        });
    }

    public function show(Request $request, $id)
    {
        $companyId = Tenant::id();

        $appointment = Appointment::query()
            ->with([
                'patient:id,name,email,company_id',
                'doctor:id,name,company_id,work_start,work_end,slot_minutes',
                'invoice:id,number,appointment_id,treatment_plan_id,status,total'
            ])
            ->findOrFail($id);

        // ✅ التحقق من الصلاحية
        $this->authorize('view', $appointment);

        $planItem = \App\Models\TreatmentPlanItem::query()
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
                'invoice_id' => $appointment->invoice?->id,
                'invoice_number' => $appointment->invoice?->number,
                'invoice_status' => $appointment->invoice?->status,
                'invoice_total' => $appointment->invoice?->total,
                'treatment_plan_id' => $appointment->invoice?->treatment_plan_id,
                'treatment_plan_item_id' => $planItem?->id,
                'reminder_status' => $appointment->reminder_status,
                'last_reminder_at' => $appointment->last_reminder_at,
                'next_reminder_at' => $appointment->next_reminder_at,
                'reminder_sent_count' => (int) ($appointment->reminder_sent_count ?? 0),
                'reminder_stage' => $appointment->reminder_stage,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $appointment = Appointment::query()->findOrFail($id);

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
            Tenant::id(),
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
        $appointment = Appointment::query()->findOrFail($id);
        $appointment->delete();

        return response()->json([
            'msg' => 'Appointment deleted',
            'status' => 200,
            'data' => null,
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $companyId = Tenant::id();

        $appointment = Appointment::query()->findOrFail($id);

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

        $appointment->update([
            'status' => 'cancelled',
            ...$this->markReminderNotNeeded(),
        ]);

        event(new DashboardUpdated(
            $companyId,
            'appointment_cancelled',
            [
                'cancelled_today_count' => 1,
                'scheduled_today_count' => -1,
            ]
        ));

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
        $companyId = Tenant::id();

        $appointment = Appointment::query()->findOrFail($id);

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

        $appointment->update([
            'status' => 'no_show',
            ...$this->markReminderNotNeeded(),
        ]);

        event(new DashboardUpdated(
            $companyId,
            'appointment_no_show',
            [
                'no_show_today_count' => 1,
                'scheduled_today_count' => -1,
            ]
        ));

        $insight = app(InsightService::class)->missedAppointmentsInsight($companyId);
        if ($insight) {
            event(new InsightGenerated($companyId, $insight));
        }

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
        $companyId = Tenant::id();

        $appointment = Appointment::query()->findOrFail($id);

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
                Rule::exists('doctors', 'id')->where('is_active', true),
            ],
        ]);

        $newDate = Carbon::parse($data['appointment_date'])->toDateString();
        $newTime = $data['appointment_time'];
        $newDoctorId = (int) $data['doctor_id'];

        $doctor = Doctor::query()
            ->where('is_active', true)
            ->findOrFail($newDoctorId);

        $this->validateAppointmentDateTime($doctor, $newDate, $newTime);

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
                'old_status' => $from->status,
                'old_doctor_id' => (int) $from->doctor_id,
                'old_date' => Carbon::parse($from->appointment_date)->toDateString(),
                'old_time' => substr((string) $from->appointment_time, 0, 5),
            ];

            $newDoctorName = ((int) $from->doctor_id === (int) $newDoctorId)
                ? $from->doctor_name
                : ($doctor->name ?? 'Doctor');

            if ($to && $to->status === 'cancelled') {
                $to->update([
                    'patient_id' => $from->patient_id,
                    'doctor_id' => $newDoctorId,
                    'doctor_name' => $newDoctorName,
                    'appointment_date' => $newDate,
                    'appointment_time' => $newTime,
                    'status' => 'scheduled',
                    'notes' => $from->notes,
                    'created_by' => $request->user()->id,
                    ...$this->buildPendingReminder($newDate, $newTime),
                ]);

                $from->update([
                    'status' => 'cancelled',
                    ...$this->markReminderNotNeeded(),
                ]);

                ActivityLogger::log(
                    $companyId,
                    $request->user(),
                    'appointment.rescheduled',
                    Appointment::class,
                    $to->id,
                    array_merge($old, [
                        'new_status' => 'scheduled',
                        'new_doctor_id' => $newDoctorId,
                        'new_date' => $newDate,
                        'new_time' => $newTime,
                        'patient_id' => $to->patient_id,
                        'from_appointment_id' => $from->id,
                        'to_appointment_id' => $to->id,
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

            try {
                $from->update([
                    'doctor_id' => $newDoctorId,
                    'doctor_name' => $newDoctorName,
                    'appointment_date' => $newDate,
                    'appointment_time' => $newTime,
                    'status' => 'scheduled',
                    ...$this->buildPendingReminder($newDate, $newTime),
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
                    'new_status' => 'scheduled',
                    'new_doctor_id' => $newDoctorId,
                    'new_date' => $newDate,
                    'new_time' => $newTime,
                    'patient_id' => $from->patient_id,
                    'from_appointment_id' => $from->id,
                    'to_appointment_id' => $from->id,
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

    public function book(Request $request)
    {
        $companyId = Tenant::id();

        $data = $request->validate([
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id'),
            ],
            'doctor_id' => ['nullable', 'integer'],
            'doctor_name' => ['nullable', 'string', 'max:190'],
            'appointment_type' => ['nullable', 'in:consultation'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string'],
        ]);

        $appointmentType = $data['appointment_type'] ?? 'consultation';

        $date = Carbon::parse($data['appointment_date'])->toDateString();
        $time = $data['appointment_time'];

        if (!empty($data['doctor_id'])) {
            $doctor = Doctor::query()
                ->where('is_active', true)
                ->findOrFail((int) $data['doctor_id']);
        } else {
            $doctor = Doctor::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if (!$doctor) {
                return response()->json([
                    'msg' => 'No active doctor found.',
                    'status' => 422,
                ], 422);
            }
        }

        $doctorId = (int) $doctor->id;
        $doctorName = trim((string) ($data['doctor_name'] ?? '')) ?: $doctor->name;

        $this->validateAppointmentDateTime($doctor, $date, $time);

        return DB::transaction(function () use (
            $request,
            $companyId,
            $data,
            $date,
            $time,
            $doctorId,
            $doctorName,
            $appointmentType
        ) {
            $existing = Appointment::query()
                ->where('doctor_id', $doctorId)
                ->whereDate('appointment_date', $date)
                ->whereTime('appointment_time', $time)
                ->lockForUpdate()
                ->first();

            if ($existing && in_array($existing->status, ['scheduled', 'completed', 'no_show'], true)) {
                return response()->json([
                    'msg' => 'Time slot already booked',
                    'status' => 422,
                    'errors' => [
                        'appointment_time' => ['This time slot is already booked'],
                    ],
                ], 422);
            }

            if ($existing && $existing->status === 'cancelled') {
                $existing->update([
                    'patient_id' => $data['patient_id'],
                    'doctor_name' => $doctorName,
                    'status' => 'scheduled',
                    'notes' => $data['notes'] ?? null,
                    'appointment_type' => $appointmentType,
                    'created_by' => $request->user()->id,
                    ...$this->buildPendingReminder($date, $time),
                ]);

                $this->createConsultationInvoiceIfMissing($existing, $request);

                ActivityLogger::log(
                    $companyId,
                    $request->user(),
                    'appointment.rebooked',
                    Appointment::class,
                    $existing->id,
                    [
                        'appointment_type' => $appointmentType,
                        'patient_id' => $existing->patient_id,
                    ]
                );

                return response()->json([
                    'msg' => 'Appointment rebooked',
                    'status' => 200,
                    'data' => $existing->fresh(),
                ]);
            }

            $appointment = Appointment::create([
                'company_id' => $companyId,
                'patient_id' => $data['patient_id'],
                'doctor_id' => $doctorId,
                'doctor_name' => $doctorName,
                'appointment_date' => $date,
                'appointment_time' => $time,
                'appointment_type' => $appointmentType,
                'status' => 'scheduled',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
                ...$this->buildPendingReminder($date, $time),
            ]);

            $this->createConsultationInvoiceIfMissing($appointment, $request);

            ActivityLogger::log(
                $companyId,
                $request->user(),
                'appointment.booked',
                Appointment::class,
                $appointment->id,
                [
                    'appointment_type' => $appointmentType,
                    'patient_id' => $appointment->patient_id,
                ]
            );

            event(new DashboardUpdated(
                $companyId,
                'appointment_created',
                [
                    'today_appointments_count' => 1,
                    'scheduled_today_count' => 1,
                ]
            ));

            return response()->json([
                'msg' => 'Appointment booked',
                'status' => 201,
                'data' => $appointment,
            ], 201);
        });
    }

    private function createConsultationInvoiceIfMissing($appointment, $request)
    {
        $companyId = $appointment->company_id;

        $exists = Invoice::query()
            ->where('appointment_id', $appointment->id)
            ->exists();

        if ($exists) return;

        $product = Product::query()
            ->where('title_en', 'Consultation')
            ->first();

        if (!$product) return;

        $price = (float) ($product->unit_price ?? 0);
        if ($price <= 0) return;

        $order = Order::create([
            'company_id' => $companyId,
            'customer_id' => $appointment->patient_id,
            'title_en' => 'Consultation Visit',
            'status' => 'confirmed',
            'total' => $price,
            'created_by' => $request->user()->id,
        ]);

        OrderItem::create([
            'company_id' => $companyId,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $price,
            'total' => $price,
        ]);

        $invoice = Invoice::create([
            'company_id' => $companyId,
            'number' => app(InvoiceNumberService::class)->generate($companyId),
            'order_id' => $order->id,
            'appointment_id' => $appointment->id,
            'customer_id' => $appointment->patient_id,
            'total' => $price,
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);

        InvoiceItem::create([
            'company_id' => $companyId,
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $price,
            'total' => $price,
        ]);

        CustomerLedgerEntry::create([
            'company_id' => $companyId,
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'type' => 'invoice',
            'debit' => $invoice->total,
            'credit' => 0,
            'entry_date' => now(),
            'description' => 'Consultation invoice #' . $invoice->number,
        ]);

        $this->autoApplyCustomerCredit($invoice, $request->user());
    }

    public function complete(Request $request, $id)
    {
        $companyId = Tenant::id();

        $appointment = Appointment::query()->findOrFail($id);

        $data = $request->validate([
            'doctor_name' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],
            'clinical_notes' => ['nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'next_step' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($request, $companyId, $data, $appointment) {
            $appointment = Appointment::query()
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

            if ($appointmentType === 'consultation') {
                $existingConsultationInvoice = Invoice::query()
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
                    ...$this->markReminderNotNeeded(),
                    ...$this->initFollowUp(),
                ]);

                event(new DashboardUpdated(
                    $companyId,
                    'appointment_completed',
                    [
                        'completed_today_count' => 1,
                        'scheduled_today_count' => -1,
                    ]
                ));

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

            if ($appointmentType === 'treatment') {
                $linkedPlanItem = \App\Models\TreatmentPlanItem::query()
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

                $plan = TreatmentPlan::query()->findOrFail($linkedPlanItem->treatment_plan_id);

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
                    ->where('appointment_id', $appointment->id)
                    ->whereHas('order', function ($q) {
                        $q->where('title_en', 'Appointment Service');
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
                    'title_en' => 'Appointment Service',
                    'title_ar' => 'خدمة موعد',
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

                $this->autoApplyCustomerCredit($invoice, $request->user());
                $invoice->refresh();

                $appointment->update([
                    'status' => 'completed',
                    ...$this->markReminderNotNeeded(),
                    ...$this->initFollowUp(),
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

                $dentalRecordCreated = false;
                $dentalRecordUpdated = false;

                if (!empty($linkedPlanItem->tooth_number)) {
                    $existingDentalRecord = DentalRecord::query()
                        ->where('treatment_plan_item_id', $linkedPlanItem->id)
                        ->lockForUpdate()
                        ->first();

                    $dentalRecordStatus = $remainingAfterComplete > 0 ? 'in_progress' : 'completed';
                    $dentalRecordNotes = $data['clinical_notes'] ?? $data['notes'] ?? $linkedPlanItem->notes;

                    if ($existingDentalRecord) {
                        $existingDentalRecord->update([
                            'appointment_id' => $appointment->id,
                            'doctor_id' => $appointment->doctor_id,
                            'procedure_id' => $linkedPlanItem->procedure_id,
                            'tooth_number' => $linkedPlanItem->tooth_number,
                            'surface' => $linkedPlanItem->surface,
                            'status' => $dentalRecordStatus,
                            'notes' => $dentalRecordNotes,
                        ]);

                        $dentalRecordUpdated = true;
                    } else {
                        DentalRecord::create([
                            'company_id' => $companyId,
                            'customer_id' => $appointment->patient_id,
                            'appointment_id' => $appointment->id,
                            'doctor_id' => $appointment->doctor_id,
                            'procedure_id' => $linkedPlanItem->procedure_id,
                            'tooth_number' => $linkedPlanItem->tooth_number,
                            'surface' => $linkedPlanItem->surface,
                            'status' => $dentalRecordStatus,
                            'notes' => $dentalRecordNotes,
                            'treatment_plan_item_id' => $linkedPlanItem->id,
                        ]);

                        $dentalRecordCreated = true;
                    }
                }

                event(new DashboardUpdated(
                    $companyId,
                    'appointment_completed',
                    [
                        'completed_today_count' => 1,
                        'scheduled_today_count' => -1,
                    ]
                ));

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
                    'dental_record_created' => $dentalRecordCreated,
                    'dental_record_updated' => $dentalRecordUpdated,
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

    public function sendReminder(Request $request, $id)
    {
        $companyId = Tenant::id();

        $appointment = Appointment::query()->findOrFail($id);

        $validationError = $this->validateReminderCanBeSent($appointment);

        if ($validationError) {
            return response()->json($validationError['body'], $validationError['status']);
        }

        $sentAt = now();
        $newCount = (int) ($appointment->reminder_sent_count ?? 0) + 1;

        $appointment->update($this->buildSentReminderState($sentAt, $newCount));
        $appointment->refresh();

        ActivityLogger::log(
            $companyId,
            $request->user(),
            'appointment.reminder_sent',
            Appointment::class,
            $appointment->id,
            [
                'patient_id' => $appointment->patient_id,
                'doctor_id' => $appointment->doctor_id,
                'appointment_date' => Carbon::parse($appointment->appointment_date)->toDateString(),
                'appointment_time' => substr((string) $appointment->appointment_time, 0, 5),
                'reminder_sent_count' => $newCount,
                'sent_at' => $sentAt->toDateTimeString(),
            ]
        );

        return response()->json([
            'msg' => 'Reminder sent successfully',
            'status' => 200,
            'data' => [
                'id' => $appointment->id,
                'reminder_status' => $appointment->reminder_status,
                'last_reminder_at' => $appointment->last_reminder_at,
                'next_reminder_at' => $appointment->next_reminder_at,
                'reminder_sent_count' => (int) $appointment->reminder_sent_count,
            ],
        ], 200);
    }

    private function autoApplyCustomerCredit(Invoice $invoice, $user): void
    {
        $companyId = $invoice->company_id;

        $totalCustomerCredit = DB::table('customer_credits')
            ->where('customer_id', $invoice->customer_id)
            ->where('type', 'credit')
            ->sum('amount');

        $totalCustomerDebit = DB::table('customer_credits')
            ->where('customer_id', $invoice->customer_id)
            ->where('type', 'debit')
            ->sum('amount');

        $availableCredit = max(0, (float) $totalCustomerCredit - (float) $totalCustomerDebit);

        if ($availableCredit <= 0) {
            return;
        }

        $totalApplied = Payment::where('invoice_id', $invoice->id)
            ->sum('applied_amount');

        $totalRefunded = DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->where('payments.invoice_id', $invoice->id)
            ->where('payment_refunds.applies_to', 'invoice')
            ->sum('payment_refunds.amount');

        $totalCreditApplied = DB::table('customer_credits')
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

        $arAccount = \App\Models\Account::where('code', '1100')->first();
        $creditAccount = \App\Models\Account::where('code', '2100')->first();

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

    private function initFollowUp(): array
    {
        return [
            'follow_up_status' => 'pending',
            'follow_up_state' => 'pending',
            'follow_up_retry_count' => 0,
            'follow_up_next_retry_at' => null,
            'follow_up_at' => now()->addHour(),
        ];
    }
}
