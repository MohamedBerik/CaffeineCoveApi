<?php

namespace App\Traits;

use App\Models\Appointment;
use Carbon\Carbon;

trait HandlesAppointmentFollowUps
{
    protected function resolveFollowUpAt(string $date, string $time): Carbon
    {
        return Carbon::parse("$date $time")->addDay();
    }

    protected function buildPendingFollowUp(string $date, string $time): array
    {
        return [
            'follow_up_state' => 'pending',
            'follow_up_at' => $this->resolveFollowUpAt($date, $time),
            'follow_up_sent_at' => null,
            'follow_up_retry_count' => 0,
            'follow_up_next_retry_at' => null,
        ];
    }

    protected function validateFollowUpCanBeSent(Appointment $appointment): bool
    {
        // لازم يكون appointment مكتمل
        if ($appointment->status !== 'completed') {
            return false;
        }

        // ممنوع الإرسال لو انتهى أو توقف
        if (in_array($appointment->follow_up_state, ['sent', 'stopped'])) {
            return false;
        }

        // pending → يعتمد على follow_up_at
        if ($appointment->follow_up_state === 'pending') {
            return !empty($appointment->follow_up_at)
                && Carbon::parse($appointment->follow_up_at)->lte(now());
        }

        // retrying → يعتمد على next_retry_at
        if ($appointment->follow_up_state === 'retrying') {
            return !empty($appointment->follow_up_next_retry_at)
                && Carbon::parse($appointment->follow_up_next_retry_at)->lte(now());
        }

        // أي state تانية غير مسموح
        return false;
    }

    protected function markFollowUpSent(): array
    {
        return [
            'follow_up_state' => 'sent',
            'follow_up_sent_at' => now(),
            'follow_up_next_retry_at' => null,
        ];
    }

    protected function markFollowUpRetrying(int $retryCount): array
    {
        return [
            'follow_up_state' => 'retrying',
            'follow_up_retry_count' => $retryCount,
            'follow_up_next_retry_at' => now()->addMinutes(5),
        ];
    }

    protected function markFollowUpStopped(int $retryCount): array
    {
        return [
            'follow_up_state' => 'stopped',
            'follow_up_retry_count' => $retryCount,
            'follow_up_next_retry_at' => null,
        ];
    }
}
