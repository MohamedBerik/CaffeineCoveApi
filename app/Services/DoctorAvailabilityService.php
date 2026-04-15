<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Services\Tenant;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class DoctorAvailabilityService
{
    /**
     * Get doctor availability for a specific date
     *
     * @return array{
     *   doctor: array{id:int,name:string,work_start:string,work_end:string,slot_minutes:int},
     *   date: string,
     *   blocked_statuses: array<int,string>,
     *   booked_times: array<int,string>,
     *   slots: array<int,array{time:string,available:bool}>
     * }
     */
    public function getAvailability(?int $companyId, int $doctorId, string $date, bool $includeBooked = true): array
    {
        // ✅ استخدام Tenant كـ fallback
        $companyId = $companyId ?? Tenant::id();

        $date = Carbon::parse($date)->toDateString();

        $doctor = Doctor::query()
            ->where('is_active', true)
            ->findOrFail($doctorId);

        $workStart   = $doctor->work_start ?: '09:00';
        $workEnd     = $doctor->work_end ?: '21:00';
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

        // ✅ بدون where('company_id') - الـ Scope هيضيفه
        $bookedTimes = Appointment::query()
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

    /**
     * Get available slots only (simplified response)
     */
    public function getAvailableSlots(?int $companyId, int $doctorId, string $date): array
    {
        $availability = $this->getAvailability($companyId, $doctorId, $date, false);

        return [
            'doctor_id' => $availability['doctor']['id'],
            'doctor_name' => $availability['doctor']['name'],
            'date' => $availability['date'],
            'available_slots' => array_map(fn($slot) => $slot['time'], $availability['slots']),
        ];
    }

    /**
     * Check if a specific time slot is available
     */
    public function isSlotAvailable(?int $companyId, int $doctorId, string $date, string $time): bool
    {
        $availability = $this->getAvailability($companyId, $doctorId, $date, true);

        foreach ($availability['slots'] as $slot) {
            if ($slot['time'] === $time) {
                return $slot['available'];
            }
        }

        return false;
    }

    /**
     * Get availability for multiple doctors
     */
    public function getMultipleDoctorsAvailability(?int $companyId, array $doctorIds, string $date): array
    {
        $result = [];

        foreach ($doctorIds as $doctorId) {
            try {
                $result[$doctorId] = $this->getAvailability($companyId, $doctorId, $date, true);
            } catch (\Exception $e) {
                $result[$doctorId] = [
                    'error' => $e->getMessage(),
                    'available' => false,
                ];
            }
        }

        return $result;
    }

    /**
     * Get next available slot for a doctor
     */
    public function getNextAvailableSlot(?int $companyId, int $doctorId, ?string $fromDate = null): ?array
    {
        $fromDate = $fromDate ? Carbon::parse($fromDate) : today();
        $maxDays = 30; // نبحث لحد 30 يوم قدام

        for ($i = 0; $i < $maxDays; $i++) {
            $date = $fromDate->copy()->addDays($i)->toDateString();

            try {
                $availability = $this->getAvailability($companyId, $doctorId, $date, false);

                if (!empty($availability['slots'])) {
                    return [
                        'date' => $date,
                        'time' => $availability['slots'][0]['time'],
                        'doctor' => $availability['doctor'],
                    ];
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Get working hours for a doctor on a specific date
     */
    public function getWorkingHours(?int $companyId, int $doctorId, string $date): array
    {
        $availability = $this->getAvailability($companyId, $doctorId, $date, true);

        return [
            'doctor_id' => $availability['doctor']['id'],
            'doctor_name' => $availability['doctor']['name'],
            'date' => $date,
            'work_start' => $availability['doctor']['work_start'],
            'work_end' => $availability['doctor']['work_end'],
            'slot_minutes' => $availability['doctor']['slot_minutes'],
            'total_slots' => count($availability['slots']),
            'booked_slots' => count($availability['booked_times']),
            'available_slots' => count(array_filter($availability['slots'], fn($s) => $s['available'])),
        ];
    }
}
