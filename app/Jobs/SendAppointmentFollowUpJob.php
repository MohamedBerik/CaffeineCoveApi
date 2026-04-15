<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Traits\HandlesAppointmentFollowUps;
use App\Jobs\Concerns\ResetsTenantContext;
use App\Services\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAppointmentFollowUpJob implements ShouldQueue
{
    use HandlesAppointmentFollowUps, Dispatchable, Queueable, SerializesModels;
    use ResetsTenantContext;

    public int $appointmentId;
    public ?int $companyId;

    public function __construct(int $appointmentId)
    {
        $this->appointmentId = $appointmentId;

        // ✅ جلب company_id من الموعد
        $appointment = Appointment::with('patient')->find($appointmentId);
        $this->companyId = $appointment?->company_id;
    }

    public function handle(): void
    {
        $this->process();
    }

    protected function process(): void
    {
        // ✅ تعيين الـ Tenant Context
        if ($this->companyId) {
            Tenant::setId($this->companyId);
        }

        $appointment = Appointment::with('patient:id,phone')
            ->where('id', $this->appointmentId)
            ->whereIn('follow_up_state', ['pending', 'retrying'])
            ->first();

        if (!$appointment) {
            return;
        }

        // 🔒 prevent duplicate processing
        $appointment->update([
            'follow_up_state' => 'processing',
        ]);

        // ✅ validation
        if (!$this->validateFollowUpCanBeSent($appointment)) {

            Log::info('Follow-up skipped', [
                'appointment_id' => $appointment->id,
                'company_id' => $this->companyId, // ✅ إضافة company_id للـ log
                'reason' => 'Validation failed'
            ]);

            $appointment->update([
                'follow_up_state' => 'skipped',
            ]);

            return;
        }

        $phone = $appointment->patient?->phone;

        if (!$phone) {

            Log::warning('Follow-up skipped: missing phone', [
                'appointment_id' => $appointment->id,
                'company_id' => $this->companyId, // ✅ إضافة company_id للـ log
            ]);

            $retryCount = $appointment->follow_up_retry_count + 1;

            $appointment->update(
                $retryCount >= 3
                    ? $this->markFollowUpStopped($retryCount)
                    : $this->markFollowUpRetrying($retryCount)
            );

            return;
        }

        $message = "How are you feeling after your appointment?";

        try {
            app(\App\Services\Whatsapp\TwilioWhatsappService::class)
                ->send($phone, $message);

            // ✅ success
            $appointment->update([
                ...$this->markFollowUpSent(),
                'follow_up_retry_count' => 0,
                'follow_up_next_retry_at' => null,
            ]);

            Log::info('Follow-up sent successfully', [
                'appointment_id' => $appointment->id,
                'company_id' => $this->companyId, // ✅ إضافة company_id للـ log
                'phone' => $phone
            ]);
        } catch (\Exception $e) {

            Log::error('Follow-up failed', [
                'appointment_id' => $appointment->id,
                'company_id' => $this->companyId, // ✅ إضافة company_id للـ log
                'error' => $e->getMessage()
            ]);

            $retryCount = $appointment->follow_up_retry_count + 1;

            $appointment->update(
                $retryCount >= 3
                    ? $this->markFollowUpStopped($retryCount)
                    : $this->markFollowUpRetrying($retryCount)
            );

            Log::warning('Follow-up retry scheduled', [
                'appointment_id' => $appointment->id,
                'company_id' => $this->companyId, // ✅ إضافة company_id للـ log
                'retry_count' => $retryCount
            ]);
        }
    }
}
