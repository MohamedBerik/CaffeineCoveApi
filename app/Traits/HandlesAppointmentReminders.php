<?php

namespace App\Traits;

use App\Models\Appointment;
use App\Services\Tenant;
use Carbon\Carbon;

trait HandlesAppointmentReminders
{
    /**
     * Resolve next reminder datetime based on stage
     */
    protected function resolveNextReminderAt(string $date, string $time, int $stage = 1): ?Carbon
    {
        $appointment = Carbon::parse($this->cleanDateTime($date, $time));
        $now = now();

        $next = match ($stage) {
            1 => $appointment->copy()->subDay(),
            2 => $appointment->copy()->subHours(3),
            3 => $appointment->copy()->subHour(),
            4 => $appointment->copy()->subMinutes(15),
            default => null,
        };

        if (!$next || $next->lte($now)) {
            return null;
        }

        return $next->startOfMinute();
    }

    /**
     * Build initial pending reminder state
     */
    private function buildPendingReminder($date, $time): array
    {
        $appointmentDateTime = Carbon::parse($this->cleanDateTime($date, $time));
        $now = now();

        // If appointment is within 30 minutes, send reminder immediately
        if ($appointmentDateTime->diffInMinutes($now, false) <= 30) {
            return [
                'reminder_status' => 'pending',
                'reminder_stage' => 1,
                'next_reminder_at' => $now->addMinute(),
                'reminder_sent_count' => 0,
                'reminder_retry_count' => 0,
            ];
        }

        return [
            'reminder_status' => 'pending',
            'reminder_stage' => 1,
            'next_reminder_at' => $this->resolveNextReminderAt($date, $time, 1),
            'reminder_sent_count' => 0,
            'reminder_retry_count' => 0,
        ];
    }

    /**
     * Mark reminder as sent
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
     * Mark reminder as not needed
     */
    protected function markReminderNotNeeded(): array
    {
        return [
            'reminder_status' => 'not_needed',
            'last_reminder_at' => null,
            'next_reminder_at' => null,
            'reminder_stage' => null,
        ];
    }

    /**
     * Mark reminder as failed (with retry)
     */
    protected function markReminderFailed(Appointment $appointment): array
    {
        $retryCount = ($appointment->reminder_retry_count ?? 0) + 1;
        $maxRetries = 3;

        if ($retryCount >= $maxRetries) {
            return [
                'reminder_status' => 'failed',
                'reminder_retry_count' => $retryCount,
                'next_reminder_at' => null,
            ];
        }

        $delayMinutes = match ($retryCount) {
            1 => 5,
            2 => 15,
            default => 30,
        };

        return [
            'reminder_status' => 'pending',
            'reminder_retry_count' => $retryCount,
            'next_reminder_at' => now()->addMinutes($delayMinutes),
            'reminder_last_attempt_at' => now(),
        ];
    }

    /**
     * Validate if reminder can be sent
     */
    protected function validateReminderCanBeSent(Appointment $appointment): ?array
    {
        // ✅ Check appointment status
        if ($appointment->status !== 'scheduled') {
            return $this->errorResponse('Only scheduled appointments can receive reminders');
        }

        // ✅ Check reminder state
        if (in_array($appointment->reminder_status, ['sent', 'not_needed', 'completed'])) {
            return $this->errorResponse('Reminder not allowed in current state');
        }

        // ✅ Check if company can send reminders
        if (!$this->canSendReminders()) {
            return $this->errorResponse('Company cannot send reminders');
        }

        // ✅ Check appointment datetime
        $appointmentDateTime = Carbon::parse(
            $this->cleanDateTime($appointment->appointment_date, $appointment->appointment_time)
        )->startOfMinute();

        $now = now()->startOfMinute();

        if ($appointmentDateTime->lte($now)) {
            return $this->errorResponse('Appointment already passed');
        }

        $minutesBefore = $now->diffInMinutes($appointmentDateTime, false);

        if ($minutesBefore < 15) {
            return $this->errorResponse('Too late to send reminder');
        }

        // ✅ Check if reminder is due
        if (
            $appointment->next_reminder_at &&
            Carbon::parse($appointment->next_reminder_at)->gt($now)
        ) {
            return $this->errorResponse('Reminder is not due yet');
        }

        // ✅ Check if patient has phone
        if (!$appointment->patient || !$appointment->patient->phone) {
            return $this->errorResponse('Patient has no phone number');
        }

        return null;
    }

    /**
     * Check if company can send reminders
     */
    protected function canSendReminders(): bool
    {
        $companyId = Tenant::id();

        if (!$companyId) {
            return false;
        }

        $company = Tenant::company();

        if (!$company) {
            return false;
        }

        return in_array($company->status, ['active', 'trial']);
    }

    /**
     * Build error response
     */
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

    /**
     * Build sent reminder state
     */
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

    /**
     * Advance reminder to next stage
     */
    protected function advanceReminderStage(Appointment $appointment): array
    {
        $currentStage = $appointment->reminder_stage ?? 1;
        $nextStage = $currentStage + 1;

        if ($nextStage > 4) {
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
            'reminder_status' => $nextReminder ? 'pending' : 'processing',
            'reminder_stage' => $nextReminder ? $nextStage : null,
            'next_reminder_at' => $nextReminder,
        ];
    }

    /**
     * Clean date and time for parsing
     */
    private function cleanDateTime(string $date, string $time): string
    {
        $cleanDate = explode(' ', $date)[0];
        return $cleanDate . ' ' . $time;
    }

    /**
     * Get reminder message template
     */
    protected function getReminderMessage(Appointment $appointment): string
    {
        $patientName = $appointment->patient->name ?? 'Patient';
        $doctorName = $appointment->doctor_name ?? 'Doctor';
        $date = Carbon::parse($appointment->appointment_date)->format('M d, Y');
        $time = substr($appointment->appointment_time, 0, 5);

        return sprintf(
            "Reminder: %s, you have an appointment with Dr. %s on %s at %s.",
            $patientName,
            $doctorName,
            $date,
            $time
        );
    }

    /**
     * Log reminder activity
     */
    protected function logReminderActivity(Appointment $appointment, string $action, array $meta = []): void
    {
        $companyId = $appointment->company_id ?? Tenant::id();

        if (!$companyId) {
            return;
        }

        try {
            \App\Models\ActivityLog::create([
                'company_id' => $companyId,
                'user_id' => auth()->id(),
                'action' => "reminder.{$action}",
                'subject_type' => Appointment::class,
                'subject_id' => $appointment->id,
                'properties' => array_merge([
                    'patient_id' => $appointment->patient_id,
                    'doctor_id' => $appointment->doctor_id,
                    'reminder_stage' => $appointment->reminder_stage,
                    'reminder_status' => $appointment->reminder_status,
                ], $meta),
            ]);
        } catch (\Exception $e) {
            // Fail silently
        }
    }
}
