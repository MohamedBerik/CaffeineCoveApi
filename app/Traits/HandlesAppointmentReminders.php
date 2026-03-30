<?php

namespace App\Traits;

use App\Models\Appointment;
use Carbon\Carbon;

trait HandlesAppointmentReminders
{
    /**
     * احسب next reminder (قبل المعاد بيوم)
     * لو وقت التذكير فات بالفعل → null
     */
    protected function resolveNextReminderAt(string $date, string $time): ?Carbon
    {
        $appointmentDateTime = Carbon::parse("$date $time")->startOfMinute();
        $nextReminder = $appointmentDateTime->copy()->subDay()->startOfMinute();

        if ($nextReminder->lte(now()->startOfMinute())) {
            return null;
        }

        return $nextReminder;
    }

    /**
     * بناء reminder fields للحالة pending
     */
    private function buildPendingReminder($date, $time): array
    {
        $appointmentDateTime = Carbon::parse($date . ' ' . $time);
        $now = now();

        // لو المعاد قريب (أقل من 30 دقيقة)
        if ($appointmentDateTime->diffInMinutes($now, false) <= 30) {
            return [
                'reminder_status' => 'pending',
                'next_reminder_at' => $now->addMinute(), // send ASAP
                'reminder_sent_count' => 0,
            ];
        }

        return [
            'reminder_status' => 'pending',
            'next_reminder_at' => $appointmentDateTime->copy()->subMinutes(30),
            'reminder_sent_count' => 0,
        ];
    }

    /**
     * تحديث بعد إرسال reminder
     */
    protected function markReminderSent(Appointment $appointment, ?Carbon $sentAt = null): array
    {
        $sentAt ??= now();

        return [
            'reminder_status' => 'sent',
            'last_reminder_at' => $sentAt,
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

        if ($appointment->reminder_status === 'sent') {
            return [
                'status' => 422,
                'body' => [
                    'msg' => 'Reminder already sent for this appointment',
                    'status' => 422,
                ],
            ];
        }

        $appointmentDateTime = Carbon::parse($appointment->appointment_date)
            ->setTimeFromTimeString((string) $appointment->appointment_time)
            ->startOfMinute();

        $now = now()->startOfMinute();

        // ❌ لو المعاد فات أو بدأ
        if ($appointmentDateTime->lte($now)) {
            return [
                'status' => 422,
                'body' => [
                    'msg' => 'Cannot send reminder for past or ongoing appointments',
                    'status' => 422,
                ],
            ];
        }

        // ✅ الفرق بالدقايق قبل المعاد
        $minutesBefore = $now->diffInMinutes($appointmentDateTime, false);

        // ❌ لو فاضل أقل من 15 دقيقة
        if ($minutesBefore < 15) {
            return [
                'status' => 422,
                'body' => [
                    'msg' => 'Too late to send reminder',
                    'status' => 422,
                ],
            ];
        }

        // ❌ لو فيه reminder متجدول ولسه مجاش وقته
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
