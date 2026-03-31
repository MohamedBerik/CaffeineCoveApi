<?php

namespace App\Traits;

use App\Models\Appointment;
use Carbon\Carbon;

trait HandlesAppointmentReminders
{
    protected function resolveNextReminderAt(string $date, string $time, int $stage = 1): ?Carbon
    {
        $appointment = Carbon::parse("$date $time");

        $now = now();

        $next = match ($stage) {
            1 => $appointment->copy()->subDay(),
            2 => $appointment->copy()->subHour(),
            3 => $appointment->copy()->subMinutes(15),
            default => null,
        };

        if (!$next || $next->lte($now)) {
            return null;
        }

        return $next->startOfMinute();
    }

    private function buildPendingReminder($date, $time): array
    {
        $appointmentDateTime = Carbon::parse($date . ' ' . $time);
        $now = now();

        // لو المعاد قريب (أقل من 30 دقيقة)
        if ($appointmentDateTime->diffInMinutes($now, false) <= 30) {
            return [
                'reminder_status' => 'pending',
                'reminder_stage' => 1, // ✅ مهم
                'next_reminder_at' => $now->addMinute(),
                'reminder_sent_count' => 0,
            ];
        }

        return [
            'reminder_status' => 'pending',
            'reminder_stage' => 1, // ✅ مهم
            'next_reminder_at' => $this->resolveNextReminderAt($date, $time, 1), // ✅ بدل subMinutes(30)
            'reminder_sent_count' => 0,
        ];
    }

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

    protected function markReminderNotNeeded(): array
    {
        return [
            'reminder_status' => 'not_needed',
            'last_reminder_at' => null,
            'next_reminder_at' => null,
        ];
    }

    protected function validateReminderCanBeSent(Appointment $appointment): ?array
    {
        if ($appointment->status !== 'scheduled') {
            return $this->errorResponse('Only scheduled appointments can receive reminders');
        }

        if (in_array($appointment->reminder_status, ['sent', 'not_needed'])) {
            return $this->errorResponse('Reminder not allowed in current state');
        }

        $appointmentDateTime = Carbon::parse(
            $appointment->appointment_date . ' ' . $appointment->appointment_time
        )->startOfMinute();

        $now = now()->startOfMinute();

        if ($appointmentDateTime->lte($now)) {
            return $this->errorResponse('Appointment already passed');
        }

        $minutesBefore = $now->diffInMinutes($appointmentDateTime, false);

        if ($minutesBefore < 15) {
            return $this->errorResponse('Too late to send reminder');
        }

        if (
            $appointment->next_reminder_at &&
            Carbon::parse($appointment->next_reminder_at)->gt($now)
        ) {
            return $this->errorResponse('Reminder is not due yet');
        }

        return null;
    }

    private function errorResponse(string $msg): array
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

    protected function advanceReminderStage(Appointment $appointment): array
    {
        $currentStage = $appointment->reminder_stage ?? 1;
        $nextStage = $currentStage + 1;

        if ($nextStage > 3) {
            return [
                'reminder_status' => 'completed',
                'reminder_stage' => null,
                'next_reminder_at' => null,
            ];
        }

        $nextReminder = $this->resolveNextReminderAt(
            $appointment->appointment_date,
            $appointment->appointment_time,
            $nextStage
        );

        return [
            'reminder_status' => $nextReminder ? 'pending' : 'sent',
            'reminder_stage' => $nextReminder ? $nextStage : null,
            'next_reminder_at' => $nextReminder,
        ];
    }
}
