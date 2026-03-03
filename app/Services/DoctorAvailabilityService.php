<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class DoctorAvailabilityService
{
    /**
     * @return array{
     *   doctor: array{id:int,name:string,work_start:string,work_end:string,slot_minutes:int},
     *   date: string,
     *   blocked_statuses: array<int,string>,
     *   booked_times: array<int,string>,
     *   slots: array<int,array{time:string,available:bool}>
     * }
     */
    public function getAvailability(int $companyId, int $doctorId, string $date, bool $includeBooked = true): array
    {
        $date = Carbon::parse($date)->toDateString();

        $doctor = Doctor::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail($doctorId);

        $workStart   = $doctor->work_start ?: '09:00';
        $workEnd     = $doctor->work_end ?: '17:00';
        $slotMinutes = (int)($doctor->slot_minutes ?: 30);

        if ($slotMinutes <= 0) {
            throw ValidationException::withMessages([
                'slot_minutes' => ['slot_minutes must be > 0'],
            ]);
        }

        $start = Carbon::parse("$date $workStart");
        $end   = Carbon::parse("$date $workEnd");

        if ($end->lte($start)) {
            throw ValidationException::withMessages([
                'work_hours' => ['work_end must be after work_start'],
            ]);
        }

        $blockedStatuses = ['scheduled', 'completed', 'no_show'];

        $bookedTimes = Appointment::query()
            ->where('company_id', $companyId)
            ->where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', $blockedStatuses)
            ->selectRaw("TIME_FORMAT(appointment_time, '%H:%i') as t")
            ->pluck('t')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $bookedSet = array_flip($bookedTimes);

        $slots = [];
        $cursor = $start->copy();

        // slots timeline: [start, end) stepping slotMinutes
        while ($cursor->lt($end)) {
            $time = $cursor->format('H:i');
            $available = !isset($bookedSet[$time]);

            if ($includeBooked || $available) {
                $slots[] = [
                    'time' => $time,
                    'available' => $available,
                ];
            }

            $cursor->addMinutes($slotMinutes);
        }

        return [
            'doctor' => [
                'id' => (int) $doctor->id,
                'name' => (string) $doctor->name,
                'work_start' => (string) $workStart,
                'work_end' => (string) $workEnd,
                'slot_minutes' => (int) $slotMinutes,
            ],
            'date' => $date,
            'blocked_statuses' => $blockedStatuses,
            'booked_times' => $bookedTimes,
            'slots' => $slots,
        ];
    }
}
