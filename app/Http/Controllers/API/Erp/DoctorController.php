<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Appointment;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class DoctorController extends Controller
{
    public function __construct()
    {
        // $this->authorizeResource(Doctor::class, 'doctor');
    }

    public function index(Request $request)
    {
        $q = Doctor::query()->orderByDesc('id');

        if ($search = trim((string)$request->get('search', ''))) {
            $q->where('name', 'like', "%{$search}%");
        }

        return response()->json([
            'msg' => 'Doctors list',
            'status' => 200,
            'data' => $q->paginate((int)$request->get('per_page', 20)),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = Tenant::id();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:190',
                Rule::unique('doctors', 'name')
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:190'],
            'work_start' => ['nullable', 'date_format:H:i'],
            'work_end' => ['nullable', 'date_format:H:i'],
            'slot_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $doctor = Doctor::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'work_start' => $data['work_start'] ?? '09:00',
            'work_end' => $data['work_end'] ?? '21:00',
            'slot_minutes' => $data['slot_minutes'] ?? 30,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()->id ?? null,
        ]);

        return response()->json(['msg' => 'Doctor created', 'status' => 201, 'data' => $doctor], 201);
    }

    public function show(Request $request, $id)
    {
        $doctor = Doctor::query()->findOrFail($id);

        return response()->json(['msg' => 'Doctor details', 'status' => 200, 'data' => $doctor]);
    }

    public function update(Request $request, $id)
    {
        $doctor = Doctor::query()->findOrFail($id);

        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:190',
                Rule::unique('doctors', 'name')->ignore($doctor->id)
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:190'],
            'work_start' => ['nullable', 'date_format:H:i'],
            'work_end' => ['nullable', 'date_format:H:i'],
            'slot_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $doctor->update($data);

        return response()->json(['msg' => 'Doctor updated', 'status' => 200, 'data' => $doctor->fresh()]);
    }

    public function destroy(Request $request, $id)
    {
        $doctor = Doctor::query()->findOrFail($id);

        $hasAppointments = Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->exists();

        if ($hasAppointments) {
            return response()->json(['msg' => 'Cannot delete doctor with appointments'], 422);
        }

        $doctor->delete();
        return response()->json(['msg' => 'Doctor deleted', 'status' => 200]);
    }

    public function availability(Request $request, $id)
    {
        $doctor = Doctor::query()->findOrFail($id);

        $date = Carbon::parse($request->query('date', now()->toDateString()))->toDateString();

        $start = Carbon::parse("$date {$doctor->work_start}");
        $end   = Carbon::parse("$date {$doctor->work_end}");

        $slot = max(5, (int)$doctor->slot_minutes);

        $booked = Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['scheduled', 'completed', 'no_show'])
            ->pluck('appointment_time')
            ->toArray();

        $slots = [];
        $cur = $start->copy();
        while ($cur->lt($end)) {
            $t = $cur->format('H:i');
            $slots[] = [
                'time' => $t,
                'available' => !in_array($t, $booked, true),
            ];
            $cur->addMinutes($slot);
        }

        return response()->json([
            'msg' => 'Doctor availability',
            'status' => 200,
            'data' => [
                'doctor_id' => $doctor->id,
                'date' => $date,
                'work_start' => $doctor->work_start,
                'work_end' => $doctor->work_end,
                'slot_minutes' => $slot,
                'slots' => $slots,
            ]
        ]);
    }
}
