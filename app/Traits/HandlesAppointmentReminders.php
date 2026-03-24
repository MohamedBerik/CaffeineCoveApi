<?php

namespace App\Traits;

use Carbon\Carbon;

trait HandlesAppointmentReminders
{
    /**
     * احسب next reminder (قبل المعاد بيوم)
     */
    protected function resolveNextReminderAt(string $date, string $time): ?Carbon
    {
        $appointmentDateTime = Carbon::parse("$date $time")->startOfMinute();

        // قبل المعاد بيوم
        $nextReminder = $appointmentDateTime->copy()->subDay();

        // لو بقى في الماضي → نخليه الآن
        if ($nextReminder->lt(now())) {
            return now();
        }

        return $nextReminder;
    }

    /**
     * بناء reminder fields للحالة pending
     */
    protected function buildPendingReminder(string $date, string $time): array
    {
        return [
            'reminder_status' => 'pending',
            'last_reminder_at' => null,
            'next_reminder_at' => $this->resolveNextReminderAt($date, $time),
            'reminder_sent_count' => 0,
        ];
    }

    /**
     * تحديث بعد إرسال reminder
     */
    protected function markReminderSent($appointment): array
    {
        return [
            'reminder_status' => 'sent',
            'last_reminder_at' => now(),
            'next_reminder_at' => null,
            'reminder_sent_count' => (int) ($appointment->reminder_sent_count ?? 0) + 1,
        ];
    }

    /**
     * لما appointment يخلص أو يتلغى
     */
    protected function markReminderNotNeeded(): array
    {
        return [
            'reminder_status' => 'not_needed',
            'last_reminder_at' => null,
            'next_reminder_at' => null,
            'reminder_sent_count' => 0,
        ];
    }

    /**
     * Validation قبل إرسال reminder
     */
    protected function validateReminderCanBeSent($appointment): void
    {
        $appointmentDateTime = Carbon::parse(
            $appointment->appointment_date . ' ' . $appointment->appointment_time
        )->startOfMinute();

        $now = now()->startOfMinute();

        // ❌ لو المعاد فات أو بدأ
        if ($appointmentDateTime->lte($now)) {
            abort(response()->json([
                'msg' => 'Cannot send reminder for past or ongoing appointments',
                'status' => 422,
            ], 422));
        }

        // ❌ لو نفس اليوم
        if (Carbon::parse($appointment->appointment_date)->isToday()) {
            abort(response()->json([
                'msg' => 'Same-day reminders are not allowed',
                'status' => 422,
            ], 422));
        }

        // ❌ لو لسه بدري
        if (
            !empty($appointment->next_reminder_at) &&
            Carbon::parse($appointment->next_reminder_at)->gt($now)
        ) {
            abort(response()->json([
                'msg' => 'Reminder is not due yet',
                'status' => 422,
            ], 422));
        }

        // ❌ لو already not needed
        if ($appointment->reminder_status === 'not_needed') {
            abort(response()->json([
                'msg' => 'Reminder is not needed for this appointment',
                'status' => 422,
            ], 422));
        }
    }
}
