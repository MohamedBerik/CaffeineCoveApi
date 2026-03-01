<?php

namespace App\Http\Controllers\API\Erp;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DoctorAvailabilityController extends Controller
{
    /**
     * GET /api/erp/doctors/{doctorId}/availability?date=YYYY-MM-DD
     * Returns time slots with availability based on doctor's schedule + existing appointments.
     */
    public function show(Request $request, $doctorId)
    {
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = Carbon::parse($data['date'])->toDateString();

        // ✅ tenant scoped doctor
        $doctor = Doctor::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail($doctorId);

        // Doctor working hours
        $workStart = $doctor->work_start ?: '09:00';
        $workEnd = $doctor->work_end ?: '17:00';
        $slotMinutes = (int) ($doctor->slot_minutes ?: 30);

        // Build slot timeline
        $start = Carbon::parse("$date $workStart");
        $end   = Carbon::parse("$date $workEnd");

        // Guard: invalid configuration
        if ($slotMinutes <= 0) {
            return response()->json([
                'msg' => 'Invalid doctor slot configuration',
                'status' => 422,
                'errors' => [
                    'slot_minutes' => ['slot_minutes must be > 0'],
                ],
            ], 422);
        }

        if ($end->lte($start)) {
            return response()->json([
                'msg' => 'Invalid doctor working hours',
                'status' => 422,
                'errors' => [
                    'work_hours' => ['work_end must be after work_start'],
                ],
            ], 422);
        }

        // ✅ booked slots for that doctor/day
        // NOTE: use statuses that should block availability
        $blockedStatuses = ['scheduled', 'completed', 'no_show'];

        $bookedTimes = Appointment::query()
            ->where('company_id', $companyId)
            ->where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', $blockedStatuses)
            ->selectRaw("TIME_FORMAT(appointment_time, '%H:%i') as t")
            ->pluck('t')
            ->filter()      // safety
            ->unique()
            ->values()
            ->all();

        $bookedSet = array_flip($bookedTimes);

        // Generate slots: [start, end) stepping slotMinutes
        $slots = [];
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $time = $cursor->format('H:i');

            // If slot start equals end => stop (but we already cursor < end)
            $available = !isset($bookedSet[$time]);

            $slots[] = [
                'time' => $time,
                'available' => $available,
            ];

            $cursor->addMinutes($slotMinutes);
        }

        return response()->json([
            'msg' => 'Doctor availability',
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
