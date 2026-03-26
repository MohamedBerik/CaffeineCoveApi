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
            'follow_up_status' => 'pending',
            'follow_up_at' => $this->resolveFollowUpAt($date, $time),
            'follow_up_sent_at' => null,
        ];
    }

    protected function validateFollowUpCanBeSent(Appointment $appointment): bool
    {
        if ($appointment->status !== 'completed') {
            return false;
        }

        if ($appointment->follow_up_status === 'sent') {
            return false;
        }

        if (
            empty($appointment->follow_up_at) ||
            Carbon::parse($appointment->follow_up_at)->gt(now())
        ) {
            return false;
        }

        return true;
    }

    protected function markFollowUpSent(): array
    {
        return [
            'follow_up_status' => 'sent',
            'follow_up_sent_at' => now(),
        ];
    }
}
