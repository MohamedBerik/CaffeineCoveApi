<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

trait ValidatesAppointments
{
    protected function validateAppointmentDateTime($doctor, string $date, string $time): void
    {
        $requested = Carbon::parse("$date $time")->startOfMinute();
        $now = now()->startOfMinute();

        if ($requested->lte($now)) {
            throw ValidationException::withMessages([
                'appointment_time' => ['Appointment must be in the future'],
            ]);
        }

        $start = Carbon::parse("$date " . ($doctor->work_start ?? '09:00'))->startOfMinute();
        $end = Carbon::parse("$date " . ($doctor->work_end ?? '17:00'))->startOfMinute();

        if ($end->lte($start)) {
            throw ValidationException::withMessages([
                'appointment_time' => ['Invalid doctor working hours'],
            ]);
        }

        if ($requested->lt($start) || $requested->gte($end)) {
            throw ValidationException::withMessages([
                'appointment_time' => ['Outside working hours'],
            ]);
        }

        $slotMinutes = (int) ($doctor->slot_minutes ?? 30);

        if ($slotMinutes <= 0) {
            throw ValidationException::withMessages([
                'appointment_time' => ['Invalid doctor slot configuration'],
            ]);
        }

        if ($start->diffInMinutes($requested) % $slotMinutes !== 0) {
            throw ValidationException::withMessages([
                'appointment_time' => ['Invalid slot interval'],
            ]);
        }
    }
}
