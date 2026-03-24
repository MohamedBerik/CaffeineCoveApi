<?php

namespace App\Traits;

use App\Models\Appointment;
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
    protected function validateReminderCanBeSent(Appointment $appointment): ?array
    {
        if ($appointment->status !== 'scheduled') {
            return [
                'status' => 422,
                'body' => [
                    'msg' => 'Only scheduled appointments can receive reminders',
                    'status' => 422,
                ],
            ];
        }

        if ($appointment->reminder_status === 'not_needed') {
            return [
                'status' => 422,
                'body' => [
                    'msg' => 'Reminder is not needed for this appointment',
                    'status' => 422,
                ],
            ];
        }

        $appointmentDateTime = Carbon::parse($appointment->appointment_date)
            ->setTimeFromTimeString((string) $appointment->appointment_time)
            ->startOfMinute();

        $now = now()->startOfMinute();

        if ($appointmentDateTime->lte($now)) {
            return [
                'status' => 422,
                'body' => [
                    'msg' => 'Cannot send reminder for past or ongoing appointments',
                    'status' => 422,
                ],
            ];
        }

        if (Carbon::parse($appointment->appointment_date)->isToday()) {
            return [
                'status' => 422,
                'body' => [
                    'msg' => 'Same-day reminders are not allowed for this appointment',
                    'status' => 422,
                ],
            ];
        }

        if (
            !empty($appointment->next_reminder_at) &&
            Carbon::parse($appointment->next_reminder_at)->gt($now)
        ) {
            return [
                'status' => 422,
                'body' => [
                    'msg' => 'Reminder is not due yet',
                    'status' => 422,
                ],
            ];
        }

        return null;
    }

    protected function buildSentReminderState(?Carbon $sentAt = null, ?int $count = null): array
    {
        $sentAt ??= now();
        $count ??= 1;

        return [
            'reminder_status' => 'sent',
            'last_reminder_at' => $sentAt,
            'next_reminder_at' => null,
            'reminder_sent_count' => $count,
        ];
    }
}
