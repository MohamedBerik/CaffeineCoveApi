<?php

namespace App\Traits;

use App\Models\Appointment;
use App\Services\Tenant;
use Carbon\Carbon;

trait HandlesAppointmentFollowUps
{
    /**
     * Resolve follow-up date/time
     */
    protected function resolveFollowUpAt(string $date, string $time, ?int $hoursAfter = 24): Carbon
    {
        return Carbon::parse("$date $time")->addHours($hoursAfter);
    }

    /**
     * Build initial follow-up state
     */
    protected function buildPendingFollowUp(string $date, string $time, ?int $hoursAfter = 24): array
    {
        return [
            'follow_up_state' => 'pending',
            'follow_up_status' => 'pending',
            'follow_up_at' => $this->resolveFollowUpAt($date, $time, $hoursAfter),
            'follow_up_sent_at' => null,
            'follow_up_retry_count' => 0,
            'follow_up_next_retry_at' => null,
        ];
    }

    /**
     * Initialize follow-up for completed appointment
     */
    protected function initFollowUp(): array
    {
        return [
            'follow_up_status' => 'pending',
            'follow_up_state' => 'pending',
            'follow_up_retry_count' => 0,
            'follow_up_next_retry_at' => null,
            'follow_up_at' => now()->addHours(24),
        ];
    }

    /**
     * Validate if follow-up can be sent
     */
    protected function validateFollowUpCanBeSent(Appointment $appointment): bool
    {
        if ($appointment->status !== 'completed') return false;
        if (!$appointment->patient || !$appointment->patient->phone) return false;
        if (in_array($appointment->follow_up_state, ['sent', 'stopped', 'skipped'])) return false;
        if (!$this->canSendFollowUp()) return false;

        if ($appointment->follow_up_state === 'pending') {
            return !empty($appointment->follow_up_at)
                && Carbon::parse($appointment->follow_up_at)->lte(now());
        }

        if ($appointment->follow_up_state === 'retrying') {
            return !empty($appointment->follow_up_next_retry_at)
                && Carbon::parse($appointment->follow_up_next_retry_at)->lte(now());
        }

        return false;
    }

    /**
     * Check if company can send follow-up messages
     */
    protected function canSendFollowUp(): bool
    {
        $companyId = Tenant::id();
        if (!$companyId) return false;

        $company = Tenant::company();
        if (!$company) return false;

        return $company->status === 'active';
    }

    /**
     * Mark follow-up as sent
     */
    protected function markFollowUpSent(): array
    {
        return [
            'follow_up_state' => 'sent',
            'follow_up_status' => 'sent',
            'follow_up_sent_at' => now(),
            'follow_up_next_retry_at' => null,
        ];
    }

    /**
     * Mark follow-up for retry
     */
    protected function markFollowUpRetrying(int $retryCount): array
    {
        $delayMinutes = match ($retryCount) {
            1 => 5,
            2 => 15,
            default => 30,
        };

        return [
            'follow_up_state' => 'retrying',
            'follow_up_status' => 'retrying',
            'follow_up_retry_count' => $retryCount,
            'follow_up_next_retry_at' => now()->addMinutes($delayMinutes),
        ];
    }

    /**
     * Mark follow-up as stopped (max retries reached)
     */
    protected function markFollowUpStopped(int $retryCount): array
    {
        return [
            'follow_up_state' => 'stopped',
            'follow_up_status' => 'failed',
            'follow_up_retry_count' => $retryCount,
            'follow_up_next_retry_at' => null,
        ];
    }

    /**
     * Mark follow-up as skipped (validation failed)
     */
    protected function markFollowUpSkipped(string $reason = 'validation_failed'): array
    {
        return [
            'follow_up_state' => 'skipped',
            'follow_up_status' => 'skipped',
            'follow_up_next_retry_at' => null,
        ];
    }

    /**
     * Mark reminder as not needed
     */
    protected function markReminderNotNeeded(): array
    {
        return [
            'reminder_status' => 'not_needed',
            'next_reminder_at' => null,
        ];
    }

    /**
     * Get follow-up message template
     */
    protected function getFollowUpMessage(Appointment $appointment): string
    {
        $patientName = $appointment->patient->name ?? 'Patient';

        $messages = [
            "Hi {$patientName}, how are you feeling after your appointment? We hope everything is well.",
            "Hello {$patientName}, just checking in! How was your recent visit?",
            "Hi {$patientName}, we care about your recovery. How are you doing today?",
        ];

        return $messages[array_rand($messages)];
    }

    /**
     * Log follow-up activity (مع إضافة branch_id)
     */
    protected function logFollowUpActivity(Appointment $appointment, string $action, array $meta = []): void
    {
        $companyId = $appointment->company_id ?? Tenant::id();
        $branchId = $appointment->branch_id ?? null; // ✅ جلب branch_id من الموعد

        if (!$companyId) return;

        try {
            \App\Models\ActivityLog::create([
                'company_id' => $companyId,
                'branch_id'  => $branchId, // ✅ تسجيل الفرع
                'user_id'    => auth()->id(),
                'action'     => "follow_up.{$action}",
                'subject_type' => Appointment::class,
                'subject_id'   => $appointment->id,
                'properties'   => array_merge([
                    'patient_id'        => $appointment->patient_id,
                    'follow_up_state'   => $appointment->follow_up_state,
                    'branch_id'         => $branchId, // ✅ إضافته للخصائص
                ], $meta),
            ]);
        } catch (\Exception $e) {
            // Fail silently
        }
    }
}
