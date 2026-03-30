<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\ActivityLogger;
use App\Traits\HandlesAppointmentReminders;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAppointmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HandlesAppointmentReminders;

    public int $appointmentId;
    public ?int $userId;

    public $tries = 3;
    public $timeout = 120;

    public function __construct(int $appointmentId, ?int $userId = null)
    {
        $this->appointmentId = $appointmentId;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        DB::transaction(function () {

            $appointment = Appointment::query()
                ->with('patient:id,phone')
                ->lockForUpdate()
                ->find($this->appointmentId);

            if (!$appointment) {
                return;
            }

            // ✅ لازم يكون processing
            if ($appointment->reminder_status !== 'processing') {
                return;
            }

            $validationError = $this->validateReminderCanBeSent($appointment);

            if ($validationError) {
                $appointment->update([
                    'reminder_status' => 'skipped'
                ]);
                return;
            }

            $phone = $appointment->patient?->phone;

            if (!$phone) {
                $this->handleFailure($appointment);
                return;
            }

            $message = sprintf(
                "Reminder: you have an appointment on %s at %s with doctor %s.",
                $appointment->appointment_date,
                substr($appointment->appointment_time, 0, 5),
                $appointment->doctor_name ?? 'Doctor'
            );

            try {

                $result = app(\App\Services\Whatsapp\TwilioWhatsappService::class)
                    ->send($phone, $message);

                $appointment->update([
                    ...$this->buildSentReminderState(now(), $appointment->reminder_sent_count + 1),
                    'reminder_retry_count' => 0,
                ]);
            } catch (\Exception $e) {

                $this->handleFailure($appointment);

                Log::error('Reminder failed', [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage()
                ]);
            }
        });
    }

    private function handleFailure(Appointment $appointment): void
    {
        $retry = ($appointment->reminder_retry_count ?? 0) + 1;

        if ($retry >= 3) {
            $appointment->update([
                'reminder_status' => 'failed',
                'reminder_retry_count' => $retry
            ]);
            return;
        }

        $appointment->update([
            'reminder_status' => 'pending',
            'reminder_retry_count' => $retry,
            'next_reminder_at' => now()->addMinutes(10),
        ]);
    }

    protected function resolveSystemUser(int $companyId)
    {
        if ($this->userId) {
            return \App\Models\User::query()
                ->where('company_id', $companyId)
                ->find($this->userId);
        }

        return \App\Models\User::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->first();
    }

    public function failed(Throwable $exception): void
    {
        // اختياري: log failure
        Log::error('SendAppointmentReminderJob failed', [
            'appointment_id' => $this->appointmentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
