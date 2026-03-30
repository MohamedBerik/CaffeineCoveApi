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
            return $this->error('Only scheduled appointments can receive reminders');
        }

        if (in_array($appointment->reminder_status, ['sent', 'not_needed'])) {
            return $this->error('Reminder not allowed in current state');
        }

        $appointmentDateTime = Carbon::parse(
            $appointment->appointment_date . ' ' . $appointment->appointment_time
        )->startOfMinute();

        $now = now()->startOfMinute();

        if ($appointmentDateTime->lte($now)) {
            return $this->error('Appointment already passed');
        }

        $minutesBefore = $now->diffInMinutes($appointmentDateTime, false);

        if ($minutesBefore < 15) {
            return $this->error('Too late to send reminder');
        }

        return null;
    }

    private function error(string $msg): array
    {
        return [
            'status' => 422,
            'body' => [
                'msg' => $msg,
                'status' => 422,
            ],
        ];
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
