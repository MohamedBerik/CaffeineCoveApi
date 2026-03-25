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

            $validationError = $this->validateReminderCanBeSent($appointment);
            if ($validationError) {
                return;
            }

            $userPhone = $appointment->patient?->phone;
            if (empty($userPhone)) {
                return;
            }

            $sentAt = now()->startOfMinute();
            $newCount = (int) ($appointment->reminder_sent_count ?? 0) + 1;

            $message = sprintf(
                "Reminder: you have an appointment on %s at %s with doctor %s.",
                Carbon::parse($appointment->appointment_date)->format('Y-m-d'),
                substr((string) $appointment->appointment_time, 0, 5),
                $appointment->doctor_name ?? 'Doctor'
            );

            $result = app(\App\Services\Whatsapp\TwilioWhatsappService::class)
                ->send($userPhone, $message);

            $appointment->update(
                $this->buildSentReminderState($sentAt, $newCount)
            );

            $appointment->refresh();

            ActivityLogger::log(
                $appointment->company_id,
                $this->resolveSystemUser($appointment->company_id),
                'appointment.reminder_sent',
                Appointment::class,
                $appointment->id,
                [
                    'patient_id' => $appointment->patient_id,
                    'doctor_id' => $appointment->doctor_id,
                    'appointment_date' => Carbon::parse($appointment->appointment_date)->toDateString(),
                    'appointment_time' => substr((string) $appointment->appointment_time, 0, 5),
                    'reminder_sent_count' => $newCount,
                    'sent_at' => $sentAt->toDateTimeString(),
                    'source' => 'scheduler',
                    'provider' => 'twilio',
                    'provider_message_sid' => $result['sid'] ?? null,
                ]
            );
        });
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
