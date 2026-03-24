<?php

trait ValidatesAppointments
{

    protected function validateAppointmentDateTime($doctor, string $date, string $time): void
    {
        $requested = \Carbon\Carbon::parse("$date $time")->startOfMinute();
        $now = now()->startOfMinute();

        // ❌ منع الماضي
        if ($requested->lte($now)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'appointment_time' => ['Appointment must be in the future'],
            ]);
        }

        $start = \Carbon\Carbon::parse("$date " . ($doctor->work_start ?? '09:00'))->startOfMinute();
        $end   = \Carbon\Carbon::parse("$date " . ($doctor->work_end ?? '17:00'))->startOfMinute();

        // ❌ خارج الشيفت
        if ($requested->lt($start) || $requested->gte($end)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'appointment_time' => ['Outside working hours'],
            ]);
        }

        $slotMinutes = (int) ($doctor->slot_minutes ?? 30);

        if ($slotMinutes <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'appointment_time' => ['Invalid doctor slot configuration'],
            ]);
        }

        // ❌ slot مش مظبوط
        if ($start->diffInMinutes($requested) % $slotMinutes !== 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'appointment_time' => ['Invalid slot interval'],
            ]);
        }
    }
}
