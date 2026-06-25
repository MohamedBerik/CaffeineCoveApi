<?php

namespace App\Services\Erp;

use App\Constants\AlertCodes;
use App\Events\DashboardUpdated;
use App\Events\InsightGenerated;
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
use App\Models\SystemAlert;
use App\Models\TreatmentPlan;
use App\Services\ActivityLogger;
use App\Services\AlertRecipientService;
use App\Services\AlertService;
use App\Services\InsightService;
use App\Services\InvoiceNumberService;
use App\Services\Tenant;
use App\Traits\ValidatesAppointments;
use App\Traits\HandlesAppointmentReminders;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    use ValidatesAppointments, HandlesAppointmentReminders;

    // اسم المنتج الموحد للعلاج
    protected const TREATMENT_PRODUCT_TITLE = 'Treatment';

    public function list(Request $request)
    {
        $user = $request->user();

        $query = Appointment::withoutGlobalScope(\App\Models\Concerns\BranchScope::class)
            ->with([
                'patient:id,name,email,company_id',
                'doctor:id,name,company_id,branch_id,work_start,work_end,slot_minutes,user_id',
                'invoice:id,appointment_id,treatment_plan_id,number,status,total',
            ]);

        if ($user->role === 'doctor') {
            $doctor = Doctor::where('user_id', $user->id)->first();
            if ($doctor) {
                $query->where('appointments.doctor_id', $doctor->id);
            } else {
                // ✅ إرجاع استعلام فارغ مع الحفاظ على نفس النطاق (بدون Global Scope)
                return $query->whereRaw('1=0')->paginate();
            }
        }

        $query->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time');

        if (!$user->is_super_admin && $user->branch_id !== null) {
            $query->where('appointments.branch_id', $user->branch_id);
        }

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

        return $query->paginate((int) $request->get('per_page', 20));
    }

    public function show($id)
    {
        return Appointment::query()
            ->with([
                'patient:id,name,email,company_id',
                'doctor:id,name,company_id,work_start,work_end,slot_minutes',
                'invoice:id,number,appointment_id,treatment_plan_id,status,total'
            ])
            ->findOrFail($id);
    }

    public function store(Request $request)
    {
        $companyId = Tenant::id();

        $v = \Validator::make($request->all(), [
            'patient_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('customers', 'id')],
            'doctor_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('doctors', 'id')->where('is_active', true)],
            'doctor_name' => ['nullable', 'string', 'max:190'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'status' => ['nullable', \Illuminate\Validation\Rule::in(['scheduled', 'completed', 'cancelled', 'no_show'])],
            'notes' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json(['msg' => 'Validation required', 'status' => 422, 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();
        $date = Carbon::parse($data['appointment_date'])->toDateString();
        $time = $data['appointment_time'];

        $doctor = Doctor::query()->where('is_active', true)->findOrFail((int) $data['doctor_id']);
        $doctorName = trim((string) ($data['doctor_name'] ?? '')) ?: ($doctor->name ?? 'Doctor');
        $requestedStatus = $data['status'] ?? 'scheduled';
        $blockedStatuses = ['scheduled', 'completed', 'no_show'];

        $this->validateAppointmentDateTime($doctor, $date, $time);

        return DB::transaction(function () use ($request, $companyId, $data, $date, $time, $doctor, $doctorName, $blockedStatuses, $requestedStatus) {
            $existing = Appointment::query()
                ->where('doctor_id', $doctor->id)
                ->whereDate('appointment_date', $date)
                ->whereTime('appointment_time', $time)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (in_array($existing->status, $blockedStatuses, true)) {
                    throw ValidationException::withMessages(['appointment_time' => ['This time slot is already booked for this doctor.']]);
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

                    ActivityLogger::log($companyId, $request->user(), 'appointment.rebooked', Appointment::class, $existing->id, [
                        'doctor_id' => $existing->doctor_id,
                        'patient_id' => $existing->patient_id,
                        'date' => $date,
                        'time' => substr((string) $time, 0, 5),
                    ]);

                    return $existing->fresh();
                }
            }

            try {
                $appointment = Appointment::create([
                    'company_id' => $companyId,
                    'branch_id' => Tenant::branchId() ?? $request->user()->branch_id,
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
                    throw ValidationException::withMessages(['appointment_time' => ['This time slot is already booked for this doctor.']]);
                }
                throw $e;
            }

            $this->createConsultationInvoiceIfMissing($appointment, $request);

            ActivityLogger::log($companyId, $request->user(), 'appointment.created', Appointment::class, $appointment->id, [
                'doctor_id' => $appointment->doctor_id,
                'patient_id' => $appointment->patient_id,
                'date' => $date,
                'time' => substr((string) $time, 0, 5),
                'status' => $appointment->status,
            ]);

            return $appointment;
        });
    }

    public function update(Request $request, $id)
    {
        $appointment = Appointment::query()->findOrFail($id);

        $v = \Validator::make($request->all(), [
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', \Illuminate\Validation\Rule::in(['scheduled', 'cancelled', 'no_show'])],
            'clinical_notes' => ['nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'next_step' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json(['msg' => 'Validation required', 'status' => 422, 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();

        if ($appointment->status === 'completed' && array_key_exists('status', $data)) {
            throw ValidationException::withMessages(['status' => ['Completed appointments status cannot be changed.']]);
        }

        $track = ['notes', 'clinical_notes', 'diagnosis', 'next_step', 'status'];
        $before = $appointment->only($track);

        $updateData = [];
        foreach (['notes', 'clinical_notes', 'diagnosis', 'next_step'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }
        if ($appointment->status !== 'completed' && array_key_exists('status', $data)) {
            $updateData['status'] = $data['status'];
        }

        $appointment->update($updateData);
        $appointment->refresh();
        $after = $appointment->only($track);

        $changedFields = [];
        foreach ($track as $k) {
            if ((string) ($before[$k] ?? '') !== (string) ($after[$k] ?? '')) {
                $changedFields[] = $k;
            }
        }

        ActivityLogger::log(Tenant::id(), $request->user(), 'appointment.updated', Appointment::class, $appointment->id, [
            'changed_fields' => $changedFields,
            'doctor_id' => $appointment->doctor_id,
            'patient_id' => $appointment->patient_id,
            'date' => Carbon::parse($appointment->appointment_date)->toDateString(),
            'time' => substr((string) $appointment->appointment_time, 0, 5),
            'status' => $appointment->status,
            'clinical_notes' => $appointment->clinical_notes,
            'diagnosis' => $appointment->diagnosis,
            'next_step' => $appointment->next_step,
        ]);

        return $appointment->load(['patient:id,name,email,company_id', 'doctor:id,name,company_id,work_start,work_end,slot_minutes']);
    }

    public function destroy(Request $request, $id)
    {
        $appointment = Appointment::query()->findOrFail($id);

        ActivityLogger::log(Tenant::id(), $request->user(), 'appointment.deleted', Appointment::class, $appointment->id, [
            'doctor_id' => $appointment->doctor_id,
            'patient_id' => $appointment->patient_id,
            'date' => Carbon::parse($appointment->appointment_date)->toDateString(),
            'time' => substr((string) $appointment->appointment_time, 0, 5),
            'status' => $appointment->status,
            'appointment_type' => $appointment->appointment_type,
        ]);

        $appointment->delete();

        return null; // success, no data
    }

    public function book(Request $request)
    {
        $companyId = Tenant::id();

        $data = $request->validate([
            'patient_id'       => ['required', 'integer', \Illuminate\Validation\Rule::exists('customers', 'id')],
            'doctor_id'        => ['nullable', 'integer'],
            'doctor_name'      => ['nullable', 'string', 'max:190'],
            'appointment_type' => ['nullable', 'in:consultation'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'notes'            => ['nullable', 'string'],
        ]);

        $appointmentType = $data['appointment_type'] ?? 'consultation';

        $date = Carbon::parse($data['appointment_date'])->toDateString();
        $time = $data['appointment_time'];

        if (!empty($data['doctor_id'])) {
            $doctor = Doctor::query()->where('is_active', true)->findOrFail((int) $data['doctor_id']);
        } else {
            $doctor = Doctor::query()->where('is_active', true)->orderBy('id')->first();
            if (!$doctor) {
                throw ValidationException::withMessages(['doctor_id' => ['No active doctor found.']]);
            }
        }

        $doctorId   = (int) $doctor->id;
        $doctorName = trim((string) ($data['doctor_name'] ?? '')) ?: $doctor->name;

        $this->validateAppointmentDateTime($doctor, $date, $time);

        return DB::transaction(function () use ($request, $companyId, $data, $date, $time, $doctorId, $doctorName, $appointmentType, $doctor) {
            $existing = Appointment::query()
                ->where('doctor_id', $doctorId)
                ->whereDate('appointment_date', $date)
                ->whereTime('appointment_time', $time)
                ->lockForUpdate()
                ->first();

            if ($existing && in_array($existing->status, ['scheduled', 'completed', 'no_show'], true)) {
                throw ValidationException::withMessages([
                    'appointment_time' => ['This time slot is already booked'],
                ]);
            }

            if ($existing && $existing->status === 'cancelled') {
                $existing->update([
                    'patient_id'      => $data['patient_id'],
                    'doctor_name'     => $doctorName,
                    'status'          => 'scheduled',
                    'notes'           => $data['notes'] ?? null,
                    'appointment_type' => $appointmentType,
                    'created_by'      => $request->user()->id,
                    ...$this->buildPendingReminder($date, $time),
                ]);

                $this->createConsultationInvoiceIfMissing($existing, $request);

                ActivityLogger::log($companyId, $request->user(), 'appointment.rebooked', Appointment::class, $existing->id, [
                    'appointment_type' => $appointmentType,
                    'patient_id'       => $existing->patient_id,
                ]);

                return $existing->fresh();
            }

            $appointment = Appointment::create([
                'company_id'      => $companyId,
                'branch_id'       => Tenant::branchId() ?? $request->user()->branch_id,
                'patient_id'      => $data['patient_id'],
                'doctor_id'       => $doctorId,
                'doctor_name'     => $doctorName,
                'appointment_date' => $date,
                'appointment_time' => $time,
                'appointment_type' => $appointmentType,
                'status'          => 'scheduled',
                'notes'           => $data['notes'] ?? null,
                'created_by'      => $request->user()->id,
                ...$this->buildPendingReminder($date, $time),
            ]);

            $this->createConsultationInvoiceIfMissing($appointment, $request);

            ActivityLogger::log($companyId, $request->user(), 'appointment.booked', Appointment::class, $appointment->id, [
                'appointment_type' => $appointmentType,
                'patient_id'       => $appointment->patient_id,
            ]);

            event(new DashboardUpdated($companyId, 'appointment_created', [
                'today_appointments_count' => 1,
                'scheduled_today_count'    => 1,
            ]));

            AlertService::send(
                AlertRecipientService::user($doctor->user_id),

                message: 'New appointment assigned to Dr. ' . $doctor->name,

                type: SystemAlert::TYPE_APPOINTMENT,

                priority: SystemAlert::PRIORITY_MEDIUM,

                meta: [
                    'appointment_id' => $appointment->id,
                    'patient_id'     => $appointment->patient_id,
                    'doctor_id'      => $doctor->id,
                ],

                code: AlertCodes::APPOINTMENT_CREATED,

                companyId: $appointment->company_id,

                branchId: $appointment->branch_id,
            );
            return $appointment;
        });
    }

    public function complete(Request $request, $id)
    {
        $companyId = Tenant::id();
        $branchId  = Tenant::branchId() ?? $request->user()->branch_id;

        $appointment = Appointment::query()->findOrFail($id);
        $data = $request->validate([
            'doctor_name'    => ['nullable', 'string', 'max:190'],
            'notes'          => ['nullable', 'string'],
            'clinical_notes' => ['nullable', 'string'],
            'diagnosis'      => ['nullable', 'string'],
            'next_step'      => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($request, $companyId, $data, $appointment, $branchId) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if ($appointment->status === 'completed') {
                throw ValidationException::withMessages(['status' => ['Appointment already completed']]);
            }

            if (!in_array($appointment->status, ['scheduled'], true)) {
                throw ValidationException::withMessages(['status' => ['Only scheduled appointments can be completed']]);
            }

            $appointmentType = (string) ($appointment->appointment_type ?? 'consultation');

            $appointment->update([
                'doctor_name'   => $data['doctor_name'] ?? $appointment->doctor_name,
                'notes'         => array_key_exists('notes', $data) ? $data['notes'] : $appointment->notes,
                'clinical_notes' => array_key_exists('clinical_notes', $data) ? $data['clinical_notes'] : $appointment->clinical_notes,
                'diagnosis'     => array_key_exists('diagnosis', $data) ? $data['diagnosis'] : $appointment->diagnosis,
                'next_step'     => array_key_exists('next_step', $data) ? $data['next_step'] : $appointment->next_step,
            ]);

            $result = [];

            if ($appointmentType === 'consultation') {
                $result = $this->completeConsultation($appointment, $request, $companyId);
            } elseif ($appointmentType === 'treatment') {
                $result = $this->completeTreatment($appointment, $request, $companyId, $branchId, $data);
            } else {
                throw ValidationException::withMessages(['appointment_type' => ['Unsupported appointment type.']]);
            }

            $appointment->update([
                'status' => 'completed',
                ...$this->markReminderNotNeeded(),
                ...$this->initFollowUp(),
            ]);

            event(new DashboardUpdated($companyId, 'appointment_completed', [
                'completed_today_count' => 1,
                'scheduled_today_count' => -1,
            ]));

            ActivityLogger::log($companyId, $request->user(), 'appointment.completed', Appointment::class, $appointment->id, $result);

            return $result;
        });
    }

    protected function completeConsultation($appointment, $request, $companyId)
    {
        $existingConsultationInvoice = Invoice::withoutGlobalScope(\App\Models\Concerns\BranchScope::class)
            ->where('appointment_id', $appointment->id)
            ->lockForUpdate()
            ->first();

        if (!$existingConsultationInvoice) {
            $this->createConsultationInvoiceIfMissing($appointment, $request);
            $existingConsultationInvoice = Invoice::withoutGlobalScope(\App\Models\Concerns\BranchScope::class)
                ->where('appointment_id', $appointment->id)
                ->first();
        }

        if (!$existingConsultationInvoice) {
            throw ValidationException::withMessages(['appointment' => ['This consultation appointment has no linked invoice and we were unable to create one.']]);
        }

        return [
            'invoice_id'        => $existingConsultationInvoice->id,
            'order_id'          => $existingConsultationInvoice->order_id,
            'invoice_number'    => $existingConsultationInvoice->number,
            'treatment_plan_id' => $existingConsultationInvoice->treatment_plan_id,
            'invoice_status'    => $existingConsultationInvoice->status,
            'total'             => (float) $existingConsultationInvoice->total,
        ];
    }

    protected function completeTreatment($appointment, $request, $companyId, $branchId, $data)
    {
        $linkedPlanItem = \App\Models\TreatmentPlanItem::query()
            ->where('appointment_id', $appointment->id)
            ->lockForUpdate()
            ->first();

        if (!$linkedPlanItem) {
            throw ValidationException::withMessages(['appointment' => ['This treatment appointment is not linked to a treatment plan item.']]);
        }

        if ($linkedPlanItem->status === 'completed') {
            throw ValidationException::withMessages(['status' => ['This treatment procedure is already completed']]);
        }

        $plan = TreatmentPlan::query()->findOrFail($linkedPlanItem->treatment_plan_id);

        if ((int) $plan->customer_id !== (int) $appointment->patient_id) {
            throw ValidationException::withMessages(['appointment' => ['Treatment plan customer mismatch.']]);
        }

        $existingTreatmentInvoice = Invoice::query()
            ->where('appointment_id', $appointment->id)
            ->whereHas('order', fn($q) => $q->where('title_en', self::TREATMENT_PRODUCT_TITLE))
            ->lockForUpdate()
            ->first();

        if ($existingTreatmentInvoice) {
            // ✅ رفض الفاتورة إذا كانت ملغية
            if ($existingTreatmentInvoice->status === 'cancelled') {
                throw ValidationException::withMessages(['appointment' => ['The linked treatment invoice has been cancelled.']]);
            }
            return [
                'invoice_id'             => $existingTreatmentInvoice->id,
                'order_id'               => $existingTreatmentInvoice->order_id,
                'invoice_number'         => $existingTreatmentInvoice->number,
                'treatment_plan_id'      => $existingTreatmentInvoice->treatment_plan_id,
                'treatment_plan_item_id' => $linkedPlanItem->id,
                'invoice_status'         => $existingTreatmentInvoice->status,
                'total'                  => (float) $existingTreatmentInvoice->total,
            ];
        }

        $price = (float) $linkedPlanItem->price;
        if ($price <= 0) {
            throw ValidationException::withMessages(['appointment' => ['The linked treatment item must have a valid price.']]);
        }

        $treatmentServiceProduct = \App\Models\Product::where('company_id', $companyId)
            ->where('title_en', self::TREATMENT_PRODUCT_TITLE)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->first();

        if (!$treatmentServiceProduct) {
            throw ValidationException::withMessages(['product' => [self::TREATMENT_PRODUCT_TITLE . ' product is required for treatment invoicing.']]);
        }

        $order = \App\Models\Order::create([
            'company_id'  => $companyId,
            'branch_id'   => $branchId,
            'customer_id' => $appointment->patient_id,
            'title_en'    => self::TREATMENT_PRODUCT_TITLE,
            'title_ar'    => 'علاج',
            'status'      => 'confirmed',
            'total'       => $price,
            'created_by'  => $request->user()->id,
        ]);

        \App\Models\OrderItem::create([
            'company_id' => $companyId,
            'order_id'   => $order->id,
            'product_id' => $treatmentServiceProduct->id,
            'quantity'   => 1,
            'unit_price' => $price,
            'total'      => $price,
        ]);

        $number  = app(InvoiceNumberService::class)->generate($companyId);
        $invoice = \App\Models\Invoice::create([
            'company_id'        => $companyId,
            'branch_id'         => $branchId,
            'number'            => $number,
            'order_id'          => $order->id,
            'appointment_id'    => $appointment->id,
            'treatment_plan_id' => $plan->id,
            'customer_id'       => $appointment->patient_id,
            'total'             => $price,
            'status'            => 'unpaid',
            'issued_at'         => now(),
        ]);

        \App\Models\InvoiceItem::create([
            'company_id' => $companyId,
            'invoice_id' => $invoice->id,
            'product_id' => $treatmentServiceProduct->id,
            'quantity'   => 1,
            'unit_price' => $price,
            'total'      => $price,
        ]);

        CustomerLedgerEntry::firstOrCreate(
            ['invoice_id' => $invoice->id, 'type' => 'invoice'],
            [
                'company_id' => $companyId,
                'branch_id'  => $branchId,
                'customer_id' => $invoice->customer_id,
                'debit'       => $invoice->total,
                'credit'      => 0,
                'entry_date'  => $invoice->issued_at ?? now(),
                'description' => 'Invoice issued #' . $invoice->number,
            ]
        );

        $this->autoApplyCustomerCredit($invoice, $request->user(), $branchId);
        $invoice->refresh();

        $currentCompleted = (int) ($linkedPlanItem->completed_sessions ?? 0);
        $plannedSessions  = max((int) ($linkedPlanItem->planned_sessions ?? 1), 1);
        $newCompleted     = min($currentCompleted + 1, $plannedSessions);
        $remainingAfter   = max($plannedSessions - $newCompleted, 0);

        $linkedPlanItem->update([
            'completed_sessions' => $newCompleted,
            'status'             => $remainingAfter > 0 ? 'planned' : 'completed',
            'appointment_id'     => null,
            'completed_at'       => $remainingAfter === 0 ? now() : null,
        ]);

        $dentalRecordCreated = false;
        $dentalRecordUpdated = false;

        if (!empty($linkedPlanItem->tooth_number)) {
            $existingDentalRecord = DentalRecord::query()
                ->where('treatment_plan_item_id', $linkedPlanItem->id)
                ->lockForUpdate()
                ->first();

            $dentalRecordStatus = $remainingAfter > 0 ? 'in_progress' : 'completed';
            $dentalRecordNotes  = $data['clinical_notes'] ?? $data['notes'] ?? $linkedPlanItem->notes;

            if ($existingDentalRecord) {
                $existingDentalRecord->update([
                    'appointment_id' => $appointment->id,
                    'doctor_id'      => $appointment->doctor_id,
                    'procedure_id'   => $linkedPlanItem->procedure_id,
                    'tooth_number'   => $linkedPlanItem->tooth_number,
                    'surface'        => $linkedPlanItem->surface,
                    'status'         => $dentalRecordStatus,
                    'notes'          => $dentalRecordNotes,
                ]);
                $dentalRecordUpdated = true;
            } else {
                DentalRecord::create([
                    'company_id'             => $companyId,
                    'branch_id'              => $branchId,
                    'customer_id'            => $appointment->patient_id,
                    'appointment_id'         => $appointment->id,
                    'doctor_id'              => $appointment->doctor_id,
                    'procedure_id'           => $linkedPlanItem->procedure_id,
                    'tooth_number'           => $linkedPlanItem->tooth_number,
                    'surface'                => $linkedPlanItem->surface,
                    'status'                 => $dentalRecordStatus,
                    'notes'                  => $dentalRecordNotes,
                    'treatment_plan_item_id' => $linkedPlanItem->id,
                ]);
                $dentalRecordCreated = true;
            }
        }

        return [
            'invoice_id'             => $invoice->id,
            'order_id'               => $order->id,
            'invoice_number'         => $invoice->number,
            'treatment_plan_id'      => $invoice->treatment_plan_id,
            'treatment_plan_item_id' => $linkedPlanItem->id,
            'invoice_status'         => $invoice->status,
            'total'                  => (float) $invoice->total,
            'completed_sessions'     => $newCompleted,
            'planned_sessions'       => $plannedSessions,
            'remaining_sessions'     => $remainingAfter,
            'item_status'            => $remainingAfter > 0 ? 'planned' : 'completed',
            'dental_record_created'  => $dentalRecordCreated,
            'dental_record_updated'  => $dentalRecordUpdated,
        ];
    }

    public function cancel(Request $request, $id)
    {
        $companyId   = Tenant::id();
        $appointment = Appointment::query()->findOrFail($id);

        if ($appointment->status === 'cancelled') {
            return ['msg' => 'Appointment already cancelled', 'status' => 409];
        }
        if ($appointment->status !== 'scheduled') {
            throw ValidationException::withMessages(['status' => ['Only scheduled appointments can be cancelled.']]);
        }

        $oldStatus = $appointment->status;
        $appointment->update(['status' => 'cancelled', ...$this->markReminderNotNeeded()]);

        event(new DashboardUpdated($companyId, 'appointment_cancelled', [
            'cancelled_today_count'  => 1,
            'scheduled_today_count' => -1,
        ]));

        if ($appointment->doctor && $appointment->doctor->user_id) {
            $doctor = $appointment->doctor;

            if ($doctor && $doctor->user_id) {
                AlertService::send(
                    AlertRecipientService::user($doctor->user_id),
                    message: 'Appointment cancelled',
                    type: SystemAlert::TYPE_APPOINTMENT,
                    priority: SystemAlert::PRIORITY_MEDIUM,
                    meta: [
                        'appointment_id' => $appointment->id,
                        'patient_id'     => $appointment->patient_id,
                        'doctor_id'      => $doctor->id,
                    ],
                    code: AlertCodes::APPOINTMENT_CANCELLED,   // ✅ كود صحيح
                    companyId: $appointment->company_id,
                    branchId: $appointment->branch_id,
                );
            }
        }
        ActivityLogger::log($companyId, $request->user(), 'appointment.cancelled', Appointment::class, $appointment->id, [
            'old_status' => $oldStatus,
            'new_status' => 'cancelled',
            'doctor_id'  => $appointment->doctor_id,
            'patient_id' => $appointment->patient_id,
            'date'       => Carbon::parse($appointment->appointment_date)->toDateString(),
            'time'       => substr((string) $appointment->appointment_time, 0, 5),
        ]);

        return $appointment->fresh()->load([
            'patient:id,name,email,company_id',
            'doctor:id,name,company_id,work_start,work_end,slot_minutes',
        ]);
    }

    public function noShow(Request $request, $id)
    {
        $companyId = Tenant::id();
        $appointment = Appointment::query()->findOrFail($id);

        if ($appointment->status === 'no_show') {
            return ['msg' => 'Appointment already marked as no-show', 'status' => 409];
        }
        if ($appointment->status !== 'scheduled') {
            throw ValidationException::withMessages(['status' => ['Only scheduled appointments can be marked as no-show.']]);
        }

        $oldStatus = $appointment->status;
        $appointment->update(['status' => 'no_show', ...$this->markReminderNotNeeded()]);

        event(new DashboardUpdated($companyId, 'appointment_no_show', [
            'no_show_today_count'    => 1,
            'scheduled_today_count' => -1,
        ]));

        $branchId = Tenant::branchId() ?? $request->user()->branch_id;

        $insight = app(InsightService::class)
            ->missedAppointmentsInsight($companyId);

        if ($insight) {
            event(new InsightGenerated(
                $companyId,
                $branchId,
                $insight
            ));
        }

        ActivityLogger::log($companyId, $request->user(), 'appointment.no_show', Appointment::class, $appointment->id, [
            'old_status' => $oldStatus,
            'new_status' => 'no_show',
            'doctor_id'  => $appointment->doctor_id,
            'patient_id' => $appointment->patient_id,
            'date'       => Carbon::parse($appointment->appointment_date)->toDateString(),
            'time'       => substr((string) $appointment->appointment_time, 0, 5),
        ]);

        return $appointment->fresh()->load([
            'patient:id,name,email,company_id',
            'doctor:id,name,company_id,work_start,work_end,slot_minutes',
        ]);
    }

    public function reschedule(Request $request, $id)
    {
        $companyId   = Tenant::id();
        $appointment = Appointment::query()->findOrFail($id);

        if ($appointment->status === 'completed') {
            throw ValidationException::withMessages(['appointment' => ['Completed appointments cannot be rescheduled.']]);
        }

        $data = $request->validate([
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'doctor_id'        => ['required', 'integer', \Illuminate\Validation\Rule::exists('doctors', 'id')->where('is_active', true)],
        ]);

        $newDate      = Carbon::parse($data['appointment_date'])->toDateString();
        $newTime      = $data['appointment_time'];
        $newDoctorId  = (int) $data['doctor_id'];
        $doctor       = Doctor::query()->where('is_active', true)->findOrFail($newDoctorId);
        $blockedStatuses = ['scheduled', 'completed', 'no_show'];

        $this->validateAppointmentDateTime($doctor, $newDate, $newTime);

        return DB::transaction(function () use ($request, $companyId, $appointment, $newDoctorId, $newDate, $newTime, $blockedStatuses, $doctor) {
            $from = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if ($from->status === 'completed') {
                throw ValidationException::withMessages(['appointment' => ['Completed appointments cannot be rescheduled.']]);
            }

            $fromDate = Carbon::parse($from->appointment_date)->toDateString();
            $fromTime = $from->appointment_time ? substr((string) $from->appointment_time, 0, 5) : null;

            if ((int) $from->doctor_id === (int) $newDoctorId && $fromDate === $newDate && $fromTime === $newTime) {
                return $from->fresh()->load(['patient:id,name,email,company_id', 'doctor:id,name,company_id,work_start,work_end,slot_minutes']);
            }

            $to = Appointment::query()
                ->where('doctor_id', $newDoctorId)
                ->whereDate('appointment_date', $newDate)
                ->whereTime('appointment_time', $newTime)
                ->lockForUpdate()
                ->first();

            if ($to && in_array($to->status, $blockedStatuses, true)) {
                throw ValidationException::withMessages(['appointment_time' => ['This time slot is already booked for this doctor.']]);
            }

            $old = [
                'old_status'    => $from->status,
                'old_doctor_id' => (int) $from->doctor_id,
                'old_date'      => Carbon::parse($from->appointment_date)->toDateString(),
                'old_time'      => substr((string) $from->appointment_time, 0, 5),
            ];

            $newDoctorName = ((int) $from->doctor_id === (int) $newDoctorId) ? $from->doctor_name : ($doctor->name ?? 'Doctor');

            if ($to && $to->status === 'cancelled') {
                $to->update([
                    'patient_id'      => $from->patient_id,
                    'doctor_id'       => $newDoctorId,
                    'doctor_name'     => $newDoctorName,
                    'appointment_date' => $newDate,
                    'appointment_time' => $newTime,
                    'status'          => 'scheduled',
                    'notes'           => $from->notes,
                    'created_by'      => $request->user()->id,
                    ...$this->buildPendingReminder($newDate, $newTime),
                ]);

                $from->update(['status' => 'cancelled', ...$this->markReminderNotNeeded()]);

                ActivityLogger::log($companyId, $request->user(), 'appointment.rescheduled', Appointment::class, $to->id, array_merge($old, [
                    'new_status'    => 'scheduled',
                    'new_doctor_id' => $newDoctorId,
                    'new_date'      => $newDate,
                    'new_time'      => $newTime,
                    'patient_id'    => $to->patient_id,
                    'from_appointment_id' => $from->id,
                    'to_appointment_id'   => $to->id,
                ]));

                return $to->fresh()->load(['patient:id,name,email,company_id', 'doctor:id,name,company_id,work_start,work_end,slot_minutes']);
            }

            try {
                $from->update([
                    'doctor_id'       => $newDoctorId,
                    'doctor_name'     => $newDoctorName,
                    'appointment_date' => $newDate,
                    'appointment_time' => $newTime,
                    'status'          => 'scheduled',
                    ...$this->buildPendingReminder($newDate, $newTime),
                ]);
            } catch (QueryException $e) {
                if ((string) $e->getCode() === '23000') {
                    throw ValidationException::withMessages(['appointment_time' => ['This time slot is already booked for this doctor.']]);
                }
                throw $e;
            }

            ActivityLogger::log($companyId, $request->user(), 'appointment.rescheduled', Appointment::class, $from->id, array_merge($old, [
                'new_status'    => 'scheduled',
                'new_doctor_id' => $newDoctorId,
                'new_date'      => $newDate,
                'new_time'      => $newTime,
                'patient_id'    => $from->patient_id,
                'from_appointment_id' => $from->id,
                'to_appointment_id'   => $from->id,
            ]));

            return $from->fresh()->load(['patient:id,name,email,company_id', 'doctor:id,name,company_id,work_start,work_end,slot_minutes']);
        });
    }

    public function sendReminder(Request $request, $id)
    {
        $companyId   = Tenant::id();
        $appointment = Appointment::query()->findOrFail($id);
        $validationError = $this->validateReminderCanBeSent($appointment);
        if ($validationError) {
            return response()->json($validationError['body'], $validationError['status']);
        }

        $sentAt   = now();
        $newCount = (int) ($appointment->reminder_sent_count ?? 0) + 1;
        $appointment->update($this->buildSentReminderState($sentAt, $newCount));
        $appointment->refresh();

        ActivityLogger::log($companyId, $request->user(), 'appointment.reminder_sent', Appointment::class, $appointment->id, [
            'patient_id'          => $appointment->patient_id,
            'doctor_id'           => $appointment->doctor_id,
            'appointment_date'    => Carbon::parse($appointment->appointment_date)->toDateString(),
            'appointment_time'    => substr((string) $appointment->appointment_time, 0, 5),
            'reminder_sent_count' => $newCount,
            'sent_at'             => $sentAt->toDateTimeString(),
        ]);

        return $appointment;
    }

    // -------------------------------------------
    // الدوال المساعدة (private) منقولة من الكنترولر
    // -------------------------------------------

    private function createConsultationInvoiceIfMissing($appointment, $request)
    {
        $companyId = $appointment->company_id;
        $branchId  = Tenant::branchId() ?? $request->user()->branch_id;

        // لو الفاتورة موجودة بالفعل لا نكرر الإنشاء
        $existingInvoice = Invoice::withoutGlobalScope(\App\Models\Concerns\BranchScope::class)
            ->where('appointment_id', $appointment->id)
            ->whereNull('treatment_plan_id')
            ->first();

        if ($existingInvoice) {
            return;
        }

        $product = Product::where('company_id', $companyId)
            ->where('title_en', 'Consultation')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->first();

        if (!$product) {
            return;
        }

        $price = (float) ($product->unit_price ?? 0);

        if ($price <= 0) {
            return;
        }

        // إنشاء Order أولاً
        $order = Order::create([
            'company_id'  => $companyId,
            'branch_id'   => $branchId,
            'customer_id' => $appointment->patient_id,
            'title_en'    => 'Consultation Visit',
            'title_ar'    => 'كشف',
            'status'      => 'confirmed',
            'total'       => $price,
            'created_by'  => $request->user()->id,
        ]);

        OrderItem::create([
            'company_id' => $companyId,
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit_price' => $price,
            'total'      => $price,
        ]);

        // إنشاء Invoice بعد وجود Order
        $invoice = Invoice::create([
            'company_id'     => $companyId,
            'branch_id'      => $branchId,
            'number'         => app(InvoiceNumberService::class)->generate($companyId),
            'order_id'       => $order->id,
            'appointment_id' => $appointment->id,
            'customer_id'    => $appointment->patient_id,
            'total'          => $price,
            'status'         => 'unpaid',
            'issued_at'      => now(),
        ]);

        InvoiceItem::create([
            'company_id' => $companyId,
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit_price' => $price,
            'total'      => $price,
        ]);

        CustomerLedgerEntry::firstOrCreate(
            [
                'invoice_id' => $invoice->id,
                'type'       => 'invoice',
            ],
            [
                'company_id'  => $companyId,
                'branch_id'   => $branchId,
                'customer_id' => $invoice->customer_id,
                'debit'       => $invoice->total,
                'credit'      => 0,
                'entry_date'  => now(),
                'description' => 'Consultation invoice #' . $invoice->number,
            ]
        );

        $this->autoApplyCustomerCredit(
            $invoice,
            $request->user(),
            $branchId
        );
    }

    private function autoApplyCustomerCredit(Invoice $invoice, $user, $branchId): void
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
            'branch_id' => $branchId,
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

        CustomerLedgerEntry::firstOrCreate(
            ['invoice_id' => $invoice->id, 'type' => 'credit_apply', 'credit' => $creditToApply],
            [
                'company_id'  => $companyId,
                'branch_id'   => $branchId,
                'customer_id' => $invoice->customer_id,
                'payment_id'  => null,
                'refund_id'   => null,
                'debit'       => 0,
                'credit'      => $creditToApply,
                'entry_date'  => now(),
                'description' => 'Customer credit auto-applied to invoice #' . $invoice->number,
            ]
        );

        $arAccount = \App\Models\Account::where('company_id', $companyId)
            ->where('code', '1000')
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

    private function initFollowUp(): array
    {
        return [
            'follow_up_status'        => 'pending',
            'follow_up_state'         => 'pending',
            'follow_up_retry_count'   => 0,
            'follow_up_next_retry_at' => null,
            'follow_up_at'            => now()->addHour(),
        ];
    }
}
