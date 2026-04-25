<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\ActivityLogger;
use App\Services\Whatsapp\TwilioWhatsappService;
use App\Traits\HandlesAppointmentReminders;
use App\Jobs\Concerns\ResetsTenantContext;
use App\Services\Tenant;
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
    use ResetsTenantContext;

    public int $appointmentId;
    public ?int $userId;
    public ?int $companyId;

    /**
     * عدد مرات إعادة المحاولة في حالة الفشل
     */
    public $tries = 3;

    /**
     * أقصى مدة للتنفيذ (بالثواني)
     */
    public $timeout = 120;
    public $maxExceptions = 3;   // ✅ وقف بعد 3 استثناءات
    public $failOnTimeout = true; // ✅ فشل لو انتهى الوقت
    /**
     * التأخير بين المحاولات الفاشلة (بالثواني)
     */
    public function backoff(): array
    {
        return [30, 120, 300]; // 30 ثانية -> دقيقتين -> 5 دقائق
    }

    /**
     * مفتاح فريد لمنع إرسال نفس التذكير مرتين
     */
    public function uniqueId(): string
    {
        return "reminder_{$this->appointmentId}";
    }

    /**
     * مدة الاحتفاظ بالقفل (Lock)
     */
    public function uniqueFor(): int
    {
        return 60; // دقيقة واحدة
    }

    public function __construct(int $appointmentId, ?int $userId = null)
    {
        $this->appointmentId = $appointmentId;
        $this->userId = $userId;

        $appointment = Appointment::with('patient')->find($appointmentId);
        $this->companyId = $appointment?->company_id;
    }

    public function handle(): void
    {
        $this->process();
    }

    protected function process(): void
    {
        if ($this->companyId) {
            Tenant::setId($this->companyId);
        }

        DB::transaction(function () {
            $appointment = Appointment::query()
                ->with('patient:id,phone,name')
                ->lockForUpdate()
                ->find($this->appointmentId);

            if (!$appointment) {
                Log::debug('Reminder job skipped: appointment not found', [
                    'appointment_id' => $this->appointmentId,
                ]);
                return;
            }

            if ($appointment->reminder_status !== 'processing') {
                Log::debug('Reminder job skipped: not in processing state', [
                    'appointment_id' => $appointment->id,
                    'current_status' => $appointment->reminder_status,
                ]);
                return;
            }

            $dedupKey = "appointment_{$appointment->id}_stage_{$appointment->reminder_stage}";

            if ($appointment->reminder_dedup_key === $dedupKey) {
                Log::debug('Reminder job skipped: duplicate (idempotency key match)', [
                    'appointment_id' => $appointment->id,
                    'dedup_key' => $dedupKey,
                ]);
                return;
            }

            // ✅ Cooldown protection
            if (
                $appointment->last_reminder_at &&
                Carbon::parse($appointment->last_reminder_at)->diffInSeconds(now()) < 30
            ) {
                Log::debug('Reminder job skipped: cooldown period', [
                    'appointment_id' => $appointment->id,
                    'last_reminder_at' => $appointment->last_reminder_at,
                ]);
                return;
            }

            $validationError = $this->validateReminderCanBeSent($appointment);

            if ($validationError) {
                $appointment->update([
                    'reminder_status' => 'skipped'
                ]);

                Log::warning('Reminder skipped', [
                    'appointment_id' => $appointment->id,
                    'company_id' => $this->companyId,
                    'patient_id' => $appointment->patient_id,
                    'stage' => $appointment->reminder_stage,
                    'reason' => $validationError['body']['msg'] ?? 'validation_failed',
                ]);

                return;
            }

            $phone = $appointment->patient?->phone;

            if (!$phone) {
                $this->handleFailure($appointment, 'missing_phone');

                Log::error('Reminder failed - missing phone', [
                    'appointment_id' => $appointment->id,
                    'company_id' => $this->companyId,
                    'patient_id' => $appointment->patient_id,
                ]);

                return;
            }

            // ✅ استخدام رسالة مخصصة من الـ Trait
            $message = $this->getReminderMessage($appointment);

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

                // ✅ تسجيل النشاط
                $this->logReminderActivity($appointment, 'sent', [
                    'phone' => $phone,
                    'stage' => $appointment->reminder_stage,
                ]);

                Log::info('Reminder sent', [
                    'appointment_id' => $appointment->id,
                    'company_id' => $this->companyId,
                    'patient_id' => $appointment->patient_id,
                    'stage' => $appointment->reminder_stage,
                    'sent_at' => now()->toDateTimeString(),
                ]);
            } catch (\Throwable $e) {
                $this->handleFailure($appointment, 'send_failed');

                $this->logReminderActivity($appointment, 'failed', [
                    'error' => $e->getMessage(),
                    'retry_count' => $appointment->reminder_retry_count,
                ]);

                Log::error('Reminder failed', [
                    'appointment_id' => $appointment->id,
                    'company_id' => $this->companyId,
                    'patient_id' => $appointment->patient_id,
                    'stage' => $appointment->reminder_stage,
                    'retry_count' => $appointment->reminder_retry_count,
                    'error' => $e->getMessage(),
                    'attempt' => $this->attempts(),
                ]);

                // ✅ إعادة رمي الاستثناء لتفعيل آلية إعادة المحاولة في Laravel
                throw $e;
            }
        });
    }

    /**
     * معالجة الفشل وإعادة المحاولة
     */
    private function handleFailure(Appointment $appointment, string $reason = 'unknown'): void
    {
        $retry = ($appointment->reminder_retry_count ?? 0) + 1;

        if ($retry >= 3) {
            $appointment->update([
                'reminder_status' => 'failed',
                'reminder_retry_count' => $retry
            ]);

            Log::warning('Reminder permanently failed', [
                'appointment_id' => $appointment->id,
                'reason' => $reason,
                'total_retries' => $retry,
            ]);

            return;
        }

        // ✅ تأخير تصاعدي حسب عدد المحاولات
        $delayMinutes = match ($retry) {
            1 => 5,
            2 => 15,
            default => 30,
        };

        $appointment->update([
            'next_reminder_at' => now()->addMinutes($delayMinutes),
            'reminder_status' => 'pending',
            'reminder_retry_count' => $retry,
            'reminder_last_attempt_at' => now(),
        ]);
    }

    /**
     * الحصول على مستخدم النظام
     */
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

    /**
     * التعامل مع فشل المهمة بشكل كامل (بعد استنفاذ المحاولات)
     */
    public function failed(Throwable $exception): void
    {
        Log::error('SendAppointmentReminderJob failed completely', [
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
            if ($appointment && $appointment->reminder_status === 'processing') {
                $appointment->update([
                    'reminder_status' => 'failed',
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
