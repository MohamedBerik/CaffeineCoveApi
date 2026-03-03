<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AppointmentAvailabilityController extends Controller
{
    /**
     * GET /api/erp/appointments/available-slots?doctor_id=1&date=YYYY-MM-DD&include_booked=0|1
     */
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'doctor_id'       => ['required', 'integer'],
            'date'            => ['required', 'date_format:Y-m-d'],
            'include_booked'  => ['nullable', 'boolean'],
        ]);

        $doctorId = (int) $data['doctor_id'];
        $date = Carbon::parse($data['date'])->toDateString();
        $includeBooked = (bool) ($data['include_booked'] ?? false);

        // ✅ tenant scoped doctor
        $doctor = Doctor::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail($doctorId);

        // Working hours + slot minutes (fallback defaults)
        $workStart   = $doctor->work_start ?: '09:00';
        $workEnd     = $doctor->work_end ?: '17:00';
        $slotMinutes = (int) ($doctor->slot_minutes ?: 30);

        if ($slotMinutes <= 0) {
            return response()->json([
                'msg' => 'Invalid doctor slot configuration',
                'status' => 422,
                'errors' => [
                    'slot_minutes' => ['slot_minutes must be > 0'],
                ],
            ], 422);
        }

        $start = Carbon::parse("$date $workStart");
        $end   = Carbon::parse("$date $workEnd");

        if ($end->lte($start)) {
            return response()->json([
                'msg' => 'Invalid working hours',
                'status' => 422,
                'errors' => [
                    'work_hours' => ['work_end must be after work_start'],
                ],
            ], 422);
        }

        // ✅ booked slots for that doctor/day (cancelled does NOT block)
        $blockedStatuses = ['scheduled', 'completed', 'no_show'];

        $bookedTimes = Appointment::query()
            ->where('company_id', $companyId)
            ->where('doctor_id', $doctorId)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', $blockedStatuses)
            ->selectRaw("TIME_FORMAT(appointment_time, '%H:%i') as t")
            ->pluck('t')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $bookedSet = array_flip($bookedTimes);

        // ✅ Generate slots: [start, end) stepping slotMinutes
        $slots = [];
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $time = $cursor->format('H:i');
            $isBooked = isset($bookedSet[$time]);

            if (!$isBooked) {
                $slots[] = ['time' => $time, 'available' => true];
            } elseif ($includeBooked) {
                $slots[] = ['time' => $time, 'available' => false];
            }

            $cursor->addMinutes($slotMinutes);
        }

        return response()->json([
            'msg' => 'Available slots',
            'status' => 200,
            'data' => [
                'doctor' => [
                    'id' => $doctor->id,
                    'name' => $doctor->name,
                    'work_start' => $workStart,
                    'work_end' => $workEnd,
                    'slot_minutes' => $slotMinutes,
                ],
                'date' => $date,
                'blocked_statuses' => $blockedStatuses,
                'booked_times' => $bookedTimes,
                'slots' => $slots,
            ],
        ]);
    }
}
