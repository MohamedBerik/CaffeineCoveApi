<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\ActivityLogger;
use App\Services\Whatsapp\TwilioWhatsappService;
use App\Traits\HandlesAppointmentReminders;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAppointmentReminderJob implements ShouldQueue, ShouldBeUnique
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

            // لازم يكون processing
            if ($appointment->reminder_status !== 'processing') {
                return;
            }

            // 🧱 Idempotency key
            $dedupKey = "appointment_{$appointment->id}_stage_{$appointment->reminder_stage}";

            // 🛑 لو اتبعت قبل كده
            if ($appointment->reminder_dedup_key === $dedupKey) {
                return;
            }

            // 🛑 cooldown protection (double trigger protection)
            if (
                $appointment->last_reminder_at &&
                Carbon::parse($appointment->last_reminder_at)->diffInSeconds(now()) < 30
            ) {
                return;
            }

            // ✅ validation
            $validationError = $this->validateReminderCanBeSent($appointment);

            if ($validationError) {
                $appointment->update([
                    'reminder_status' => 'skipped'
                ]);

                Log::warning('Reminder skipped', [
                    'appointment_id' => $appointment->id,
                    'stage' => $appointment->reminder_stage,
                    'reason' => 'validation_failed',
                ]);

                return;
            }

            $phone = $appointment->patient?->phone;

            if (!$phone) {
                $this->handleFailure($appointment);

                Log::error('Reminder failed - missing phone', [
                    'appointment_id' => $appointment->id,
                ]);

                return;
            }

            $message = sprintf(
                "Reminder: you have an appointment on %s at %s with doctor %s.",
                $appointment->appointment_date,
                substr($appointment->appointment_time, 0, 5),
                $appointment->doctor_name ?? 'Doctor'
            );

            try {

                app(TwilioWhatsappService::class)
                    ->send($phone, $message);

                $appointment->update([
                    'reminder_dedup_key' => $dedupKey,
                    'last_reminder_at' => now(),
                    'reminder_last_attempt_at' => now(),
                    'reminder_sent_count' => $appointment->reminder_sent_count + 1,
                    'reminder_retry_count' => 0,

                    ...$this->advanceReminderStage($appointment),
                ]);

                Log::info('Reminder sent', [
                    'appointment_id' => $appointment->id,
                    'stage' => $appointment->reminder_stage,
                    'phone' => $phone,
                    'sent_at' => now()->toDateTimeString(),
                ]);
            } catch (\Throwable $e) {

                $this->handleFailure($appointment);

                Log::error('Reminder failed', [
                    'appointment_id' => $appointment->id,
                    'stage' => $appointment->reminder_stage,
                    'retry_count' => $appointment->reminder_retry_count,
                    'error' => $e->getMessage(),
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
            'next_reminder_at' => now()->addMinutes(5),
            'reminder_status' => 'pending',
            'reminder_retry_count' => $retry,
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
