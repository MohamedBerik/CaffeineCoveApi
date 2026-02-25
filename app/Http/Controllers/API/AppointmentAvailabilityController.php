<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentAvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'doctor_name'       => ['required', 'string', 'max:190'],
            'date'              => ['required', 'date'],                 // YYYY-MM-DD
            'start_time'        => ['nullable', 'date_format:H:i'],      // default 09:00
            'end_time'          => ['nullable', 'date_format:H:i'],      // default 17:00
            'slot_minutes'      => ['nullable', 'integer', 'min:5', 'max:180'],
            'include_booked'    => ['nullable', 'boolean'],
        ]);

        $doctor = trim($data['doctor_name']);
        $date = Carbon::parse($data['date'])->toDateString();

        $startTime = $data['start_time'] ?? '09:00';
        $endTime   = $data['end_time']   ?? '17:00';

        $slotMinutes = (int)($data['slot_minutes'] ?? 30);
        $includeBooked = (bool)($data['include_booked'] ?? false);

        // ✅ get booked slots (ignore cancelled)
        $bookedTimes = Appointment::where('company_id', $companyId)
            ->where('doctor_name', $doctor)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['scheduled', 'completed', 'no_show'])
            ->pluck('appointment_time')
            ->map(fn($t) => Carbon::parse($t)->format('H:i'))
            ->unique()
            ->values()
            ->all();

        $bookedSet = array_flip($bookedTimes);

        // ✅ build all possible slots
        $start = Carbon::parse("$date $startTime");
        $end   = Carbon::parse("$date $endTime");

        if ($end->lessThanOrEqualTo($start)) {
            return response()->json([
                'msg' => 'Invalid working hours',
                'status' => 422,
                'errors' => [
                    'end_time' => ['end_time must be after start_time.']
                ],
            ], 422);
        }

        $slots = [];
        $cursor = $start->copy();

        while ($cursor->copy()->addMinutes($slotMinutes)->lessThanOrEqualTo($end)) {
            $time = $cursor->format('H:i');
            $isBooked = isset($bookedSet[$time]);

            if (!$isBooked) {
                $slots[] = [
                    'time' => $time,
                    'status' => 'available',
                ];
            } elseif ($includeBooked) {
                $slots[] = [
                    'time' => $time,
                    'status' => 'booked',
                ];
            }

            $cursor->addMinutes($slotMinutes);
        }

        return response()->json([
            'msg' => 'Available slots',
            'status' => 200,
            'data' => [
                'company_id' => $companyId,
                'doctor_name' => $doctor,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'slot_minutes' => $slotMinutes,
                'booked_times' => $bookedTimes,
                'slots' => $slots,
            ],
        ]);
    }
}
