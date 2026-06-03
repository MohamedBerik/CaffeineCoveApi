<?php

namespace App\Traits;

use App\Models\Doctor;
use App\Services\Tenant;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

trait ValidatesAppointments
{
    /**
     * Validate appointment date and time against doctor's schedule
     */
    protected function validateAppointmentDateTime($doctor, string $date, string $time, ?int $branchId = null): void
    {
        // ✅ Ensure doctor is active
        if (!$doctor->is_active) {
            throw ValidationException::withMessages([
                'doctor_id' => ['Selected doctor is not active'],
            ]);
        }

        // ✅ Ensure doctor belongs to current company AND branch (if branch context)
        $this->validateDoctorCompanyAndBranch($doctor, $branchId);

        $requested = Carbon::parse("$date $time")->startOfMinute();
        $now = now()->startOfMinute();

        // ✅ Appointment must be in the future
        if ($requested->lte($now)) {
            throw ValidationException::withMessages([
                'appointment_time' => ['Appointment must be in the future'],
            ]);
        }

        // ✅ Validate against booking advance limit (e.g., max 60 days ahead)
        $maxAdvanceDays = 60;
        if ($requested->diffInDays($now) > $maxAdvanceDays) {
            throw ValidationException::withMessages([
                'appointment_date' => ["Appointments can only be booked up to {$maxAdvanceDays} days in advance"],
            ]);
        }

        // ✅ Get working hours
        $workStart = $doctor->work_start ?? '09:00';
        $workEnd = $doctor->work_end ?? '21:00';

        $start = Carbon::parse("$date $workStart")->startOfMinute();
        $end = Carbon::parse("$date $workEnd")->startOfMinute();

        if ($end->lte($start)) {
            throw ValidationException::withMessages([
                'appointment_time' => ['Invalid doctor working hours configuration'],
            ]);
        }

        // ✅ Check if within working hours
        if ($requested->lt($start) || $requested->gte($end)) {
            throw ValidationException::withMessages([
                'appointment_time' => ["Appointment must be between {$workStart} and {$workEnd}"],
            ]);
        }

        // ✅ Validate slot interval
        $slotMinutes = (int) ($doctor->slot_minutes ?? 30);

        if ($slotMinutes <= 0) {
            throw ValidationException::withMessages([
                'appointment_time' => ['Invalid doctor slot configuration'],
            ]);
        }

        if ($start->diffInMinutes($requested) % $slotMinutes !== 0) {
            throw ValidationException::withMessages([
                'appointment_time' => ["Appointment time must align with {$slotMinutes}-minute slots"],
            ]);
        }

        // ✅ Check for breaks (if implemented)
        $this->validateNotDuringBreak($doctor, $requested);
    }

    /**
     * Validate that doctor belongs to current company AND branch
     */
    protected function validateDoctorCompanyAndBranch(Doctor $doctor, ?int $branchId = null): void
    {
        $companyId = Tenant::id();

        if (!$companyId) {
            throw ValidationException::withMessages([
                'doctor_id' => ['Unable to verify company context'],
            ]);
        }

        if ($doctor->company_id !== $companyId) {
            throw ValidationException::withMessages([
                'doctor_id' => ['Doctor does not belong to your company'],
            ]);
        }

        // ✅ إذا كان هناك سياق فرع محدد (branchId)، تأكد من أن الطبيب يتبع هذا الفرع
        if ($branchId && $doctor->branch_id && (int) $doctor->branch_id !== (int) $branchId) {
            throw ValidationException::withMessages([
                'doctor_id' => ['Doctor does not belong to the selected branch'],
            ]);
        }
    }

    /**
     * Validate that appointment time doesn't fall during doctor's break
     */
    protected function validateNotDuringBreak(Doctor $doctor, Carbon $requested): void
    {
        // ✅ If doctor has breaks configured
        if (empty($doctor->breaks)) {
            return;
        }

        $breaks = is_array($doctor->breaks) ? $doctor->breaks : json_decode($doctor->breaks, true);

        if (empty($breaks)) {
            return;
        }

        $requestedTime = $requested->format('H:i');

        foreach ($breaks as $break) {
            if ($requestedTime >= ($break['start'] ?? '') && $requestedTime < ($break['end'] ?? '')) {
                throw ValidationException::withMessages([
                    'appointment_time' => ['This time falls during doctor\'s break'],
                ]);
            }
        }
    }

    /**
     * Validate appointment duration against doctor's availability
     */
    protected function validateAppointmentDuration(Doctor $doctor, string $date, string $time, int $durationMinutes): void
    {
        $slotMinutes = (int) ($doctor->slot_minutes ?? 30);

        if ($durationMinutes % $slotMinutes !== 0) {
            throw ValidationException::withMessages([
                'duration' => ["Duration must be a multiple of {$slotMinutes} minutes"],
            ]);
        }

        $workStart = $doctor->work_start ?? '09:00';
        $workEnd = $doctor->work_end ?? '21:00';

        $start = Carbon::parse("$date $time");
        $end = $start->copy()->addMinutes($durationMinutes);
        $workEndTime = Carbon::parse("$date $workEnd");

        if ($end->gt($workEndTime)) {
            throw ValidationException::withMessages([
                'duration' => ['Appointment exceeds working hours'],
            ]);
        }
    }

    /**
     * Validate that patient doesn't have conflicting appointment
     */
    protected function validateNoPatientConflict(int $patientId, string $date, string $time, ?int $excludeAppointmentId = null): void
    {
        $requested = Carbon::parse("$date $time")->startOfMinute();
        $requestedEnd = $requested->copy()->addMinutes(30); // Default 30 min duration

        $query = \App\Models\Appointment::query()
            ->where('patient_id', $patientId)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['scheduled', 'confirmed']);

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        $conflicting = $query->get()->filter(function ($appointment) use ($requested, $requestedEnd) {
            $appointmentTime = Carbon::parse($appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->appointment_time);
            $appointmentEnd = $appointmentTime->copy()->addMinutes($appointment->duration ?? 30);

            return $requested->between($appointmentTime, $appointmentEnd) ||
                $appointmentTime->between($requested, $requestedEnd);
        });

        if ($conflicting->isNotEmpty()) {
            throw ValidationException::withMessages([
                'patient_id' => ['Patient already has an appointment at this time'],
            ]);
        }
    }

    /**
     * Get available slots for a doctor on a specific date (branch-scoped)
     */
    protected function getAvailableSlots(Doctor $doctor, string $date, ?int $branchId = null): array
    {
        $workStart = $doctor->work_start ?? '09:00';
        $workEnd = $doctor->work_end ?? '21:00';
        $slotMinutes = (int) ($doctor->slot_minutes ?? 30);

        $start = Carbon::parse("$date $workStart");
        $end = Carbon::parse("$date $workEnd");

        // ✅ فلترة المواعيد المحجوزة حسب الفرع
        $bookedQuery = \App\Models\Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['scheduled', 'confirmed']);

        if ($branchId) {
            $bookedQuery->where('branch_id', $branchId);
        }

        $bookedSlots = $bookedQuery
            ->pluck('appointment_time')
            ->map(fn($time) => Carbon::parse($time)->format('H:i'))
            ->toArray();

        $slots = [];
        $current = $start->copy();

        while ($current->lt($end)) {
            $timeSlot = $current->format('H:i');

            if (!in_array($timeSlot, $bookedSlots)) {
                $slots[] = $timeSlot;
            }

            $current->addMinutes($slotMinutes);
        }

        return $slots;
    }
}
