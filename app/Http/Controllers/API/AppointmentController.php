<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\CustomerLedgerEntry;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
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
            ->with(['patient:id,name,email,company_id'])
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

    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $v = Validator::make($request->all(), [
            'patient_id'        => [
                'required',
                'integer',
                // ✅ patient must belong to same company
                Rule::exists('customers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'doctor_name'       => ['required', 'string', 'max:190'], // ✅ NOT NULL now
            'appointment_date'  => ['required', 'date'],              // could be Y-m-d or full date string
            'appointment_time'  => ['required', 'date_format:H:i'],
            'status'            => ['nullable', Rule::in(['scheduled', 'completed', 'cancelled', 'no_show'])],
            'notes'             => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'msg' => 'Validation required',
                'status' => 422,
                'errors' => $v->errors(),
            ], 422);
        }

        $data = $v->validated();

        // ✅ prevent duplicate slot (friendly 422 instead of SQL exception)
        $slotTaken = Appointment::query()
            ->where('company_id', $companyId)
            ->where('doctor_name', $data['doctor_name'])
            ->whereDate('appointment_date', $data['appointment_date'])
            ->where('appointment_time', $data['appointment_time'])
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

        try {
            $appointment = Appointment::create([
                ...$data,
                'company_id'  => $companyId,
                'created_by'  => $request->user()->id,
            ]);
        } catch (QueryException $e) {
            // ✅ fallback in case of race condition (two requests same time)
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
            'data' => $appointment->load('patient:id,name,email,company_id'),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->with('patient:id,name,email,company_id')
            ->findOrFail($id);

        return response()->json([
            'msg' => 'Appointment details',
            'status' => 200,
            'data' => $appointment,
        ]);
    }

    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $v = Validator::make($request->all(), [
            'patient_id'        => [
                'sometimes',
                'integer',
                Rule::exists('customers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            // ✅ keep it required if sent, but allow partial update without sending it
            'doctor_name'       => ['sometimes', 'required', 'string', 'max:190'],
            'appointment_date'  => ['sometimes', 'date'],
            'appointment_time'  => ['sometimes', 'date_format:H:i'],
            // 'status'            => ['sometimes', Rule::in(['scheduled', 'completed', 'cancelled', 'no_show'])],
            'notes'             => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'msg' => 'Validation required',
                'status' => 422,
                'errors' => $v->errors(),
            ], 422);
        }

        $data = $v->validated();

        // ✅ if any of the slot fields updated, re-check uniqueness (ignore current id)
        $newDoctor = $data['doctor_name'] ?? $appointment->doctor_name;
        $newDate   = $data['appointment_date'] ?? $appointment->appointment_date;
        $newTime   = $data['appointment_time'] ?? $appointment->appointment_time;

        $slotTaken = Appointment::query()
            ->where('company_id', $companyId)
            ->where('doctor_name', $newDoctor)
            ->whereDate('appointment_date', $newDate)
            ->where('appointment_time', $newTime)
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
            'data' => $appointment->fresh()->load('patient:id,name,email,company_id'),
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

    public function book(Request $request)
    {
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'patient_id' => ['required', 'integer'],
            'doctor_name' => ['required', 'string', 'max:190'],
            'appointment_date' => ['required', 'date'],       // YYYY-MM-DD
            'appointment_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string'],
        ]);

        $doctor = trim($data['doctor_name']);
        $date = Carbon::parse($data['appointment_date'])->toDateString();
        $time = $data['appointment_time'];

        // V1 working hours (نفس defaults بتاعة availability)
        $startTime = '09:00';
        $endTime = '17:00';
        $slotMinutes = 30;

        $start = Carbon::parse("$date $startTime");
        $end   = Carbon::parse("$date $endTime");
        $requested = Carbon::parse("$date $time");

        // 1) time داخل حدود ساعات العمل
        if ($requested->lt($start) || $requested->gte($end)) {
            throw ValidationException::withMessages([
                'appointment_time' => ['Time is outside working hours.']
            ]);
        }

        // 2) time aligned to slot step
        $diff = $start->diffInMinutes($requested);
        if ($diff % $slotMinutes !== 0) {
            throw ValidationException::withMessages([
                'appointment_time' => ['Time must match slot interval.']
            ]);
        }

        // 3) check collision (مع نفس شروطك: scheduled/completed/no_show)
        $exists = Appointment::where('company_id', $companyId)
            ->where('doctor_name', $doctor)
            ->whereDate('appointment_date', $date)
            ->where('appointment_time', $time)
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

        $appointment = Appointment::create([
            'company_id' => $companyId,
            'patient_id' => $data['patient_id'],
            'doctor_name' => $doctor,
            'appointment_date' => $date,
            'appointment_time' => $time,
            'status' => 'scheduled',
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'msg' => 'Appointment booked',
            'status' => 201,
            'data' => $appointment->load('patient:id,name,email,company_id'),
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
            'data' => $appointment->fresh()->load('patient:id,name,email,company_id'),
        ]);
    }

    public function complete(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'total' => ['required', 'numeric', 'min:0.01'],
            'doctor_name' => ['nullable', 'string', 'max:190'], // اختياري
            'notes' => ['nullable', 'string'],
        ]);

        $appointment = Appointment::where('company_id', $companyId)->findOrFail($id);

        if ($appointment->status === 'completed') {
            return response()->json([
                'msg' => 'Appointment already completed',
                'status' => 409,
            ], 409);
        }

        // لازم يكون عندك Product للخدمة (Consultation) داخل نفس الشركة
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

            // 1) Create Order (internal order for the visit)
            $order = \App\Models\Order::create([
                'company_id'   => $companyId,
                'customer_id'  => $appointment->patient_id, // patient stored as customer
                'status'       => 'confirmed',
                'total'        => $data['total'],
                'created_by'   => $request->user()->id,
            ]);

            // 2) Create OrderItem (optional but recommended)
            \App\Models\OrderItem::create([
                'company_id'  => $companyId,
                'order_id'    => $order->id,
                'product_id'  => $serviceProduct->id,
                'quantity'    => 1,
                'unit_price'  => $data['total'],
                'total'       => $data['total'],
            ]);

            // 3) Generate invoice number
            $number = 'INV-' . now()->format('YmdHis') . '-' . $order->id;

            // 4) Create Invoice (order_id is NOT NULL عندك)
            $invoice = \App\Models\Invoice::create([
                'company_id'      => $companyId,
                'number'          => $number,
                'order_id'        => $order->id,
                'appointment_id'  => $appointment->id,
                'customer_id'     => $appointment->patient_id,
                'total'           => $data['total'],
                'status'          => 'unpaid',
                'issued_at'       => now(),
            ]);

            // 5) Create InvoiceItem (product_id NOT NULL)
            \App\Models\InvoiceItem::create([
                'company_id'  => $companyId,
                'invoice_id'  => $invoice->id,
                'product_id'  => $serviceProduct->id,
                'quantity'    => 1,
                'unit_price'  => $data['total'],
                'total'       => $data['total'],
            ]);

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
            // 6) Mark appointment completed
            $appointment->update([
                'status' => 'completed',
            ]);

            return response()->json([
                'msg' => 'Appointment completed and invoice created',
                'status' => 200,
                'invoice_id' => $invoice->id,
                'order_id' => $order->id,
                'invoice_number' => $invoice->number,
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
            'data' => $appointment->fresh()->load('patient:id,name,email,company_id'),
        ]);
    }
}
