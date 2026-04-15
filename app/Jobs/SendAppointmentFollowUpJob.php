<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Traits\HandlesAppointmentFollowUps;
use App\Jobs\Concerns\ResetsTenantContext;
use App\Services\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAppointmentFollowUpJob implements ShouldQueue, ShouldBeUnique
{
    use HandlesAppointmentFollowUps, Dispatchable, Queueable, SerializesModels;
    use ResetsTenantContext;

    public int $appointmentId;
    public ?int $companyId;

    /**
     * عدد مرات إعادة المحاولة في حالة الفشل
     */
    public $tries = 3;

    /**
     * أقصى مدة للتنفيذ (بالثواني)
     */
    public $timeout = 30;

    /**
     * التأخير بين المحاولات الفاشلة (بالثواني)
     */
    public function backoff(): array
    {
        return [30, 120, 300]; // 30 ثانية -> دقيقتين -> 5 دقائق
    }

    /**
     * مفتاح فريد لمنع إرسال نفس المتابعة مرتين
     */
    public function uniqueId(): string
    {
        return 'follow_up_' . $this->appointmentId;
    }

    /**
     * مدة الاحتفاظ بالقفل (Lock)
     */
    public function uniqueFor(): int
    {
        return 60; // دقيقة واحدة
    }

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

        // ✅ استخدام lockForUpdate لمنع التعديل المتزامن
        $appointment = Appointment::with('patient:id,phone,name')
            ->where('id', $this->appointmentId)
            ->whereIn('follow_up_state', ['pending', 'retrying'])
            ->lockForUpdate()
            ->first();

        if (!$appointment) {
            Log::debug('Follow-up job skipped: appointment not found or not in valid state', [
                'appointment_id' => $this->appointmentId,
            ]);
            return;
        }

        // ✅ تحديث الحالة لـ processing بشكل آمن
        $appointment->update([
            'follow_up_state' => 'processing',
        ]);

        // ✅ validation
        if (!$this->validateFollowUpCanBeSent($appointment)) {
            Log::info('Follow-up skipped', [
                'appointment_id' => $appointment->id,
                'company_id' => $this->companyId,
                'patient_id' => $appointment->patient_id,
                'follow_up_state' => $appointment->follow_up_state,
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
                'company_id' => $this->companyId,
                'patient_id' => $appointment->patient_id,
            ]);

            $retryCount = $appointment->follow_up_retry_count + 1;

            $appointment->update(
                $retryCount >= 3
                    ? $this->markFollowUpStopped($retryCount)
                    : $this->markFollowUpRetrying($retryCount)
            );

            return;
        }

        // ✅ استخدام رسالة مخصصة من الـ Trait
        $message = $this->getFollowUpMessage($appointment);

        try {
            app(\App\Services\Whatsapp\TwilioWhatsappService::class)
                ->send($phone, $message);

            // ✅ success
            $appointment->update([
                ...$this->markFollowUpSent(),
                'follow_up_retry_count' => 0,
                'follow_up_next_retry_at' => null,
            ]);

            // ✅ تسجيل النشاط
            $this->logFollowUpActivity($appointment, 'sent', [
                'phone' => $phone,
            ]);

            Log::info('Follow-up sent successfully', [
                'appointment_id' => $appointment->id,
                'company_id' => $this->companyId,
                'patient_id' => $appointment->patient_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Follow-up failed', [
                'appointment_id' => $appointment->id,
                'company_id' => $this->companyId,
                'patient_id' => $appointment->patient_id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            $retryCount = $appointment->follow_up_retry_count + 1;

            $appointment->update(
                $retryCount >= 3
                    ? $this->markFollowUpStopped($retryCount)
                    : $this->markFollowUpRetrying($retryCount)
            );

            $this->logFollowUpActivity($appointment, 'failed', [
                'retry_count' => $retryCount,
                'error' => $e->getMessage(),
            ]);

            Log::warning('Follow-up retry scheduled', [
                'appointment_id' => $appointment->id,
                'company_id' => $this->companyId,
                'retry_count' => $retryCount,
            ]);

            // ✅ إعادة رمي الاستثناء لتفعيل آلية إعادة المحاولة في Laravel
            throw $e;
        }
    }

    /**
     * التعامل مع فشل المهمة بشكل كامل (بعد استنفاذ المحاولات)
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendAppointmentFollowUpJob failed completely', [
            'appointment_id' => $this->appointmentId,
            'company_id' => $this->companyId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // ✅ تحديث حالة الموعد لـ failed لو لسه موجود
        try {
            if ($this->companyId) {
                Tenant::setId($this->companyId);
            }

            $appointment = Appointment::find($this->appointmentId);
            if ($appointment && $appointment->follow_up_state === 'processing') {
                $appointment->update([
                    'follow_up_state' => 'failed',
                    'follow_up_status' => 'failed',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update appointment status in failed() handler', [
                'appointment_id' => $this->appointmentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
