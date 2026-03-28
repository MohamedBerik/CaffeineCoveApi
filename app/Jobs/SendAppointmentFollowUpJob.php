<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Traits\HandlesAppointmentFollowUps;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAppointmentFollowUpJob implements ShouldQueue
{
    use HandlesAppointmentFollowUps, Dispatchable, Queueable, SerializesModels;

    public function __construct(public int $appointmentId) {}

    public function handle(): void
    {
        // 🔒 نجيب appointment (pending + failed retry)
        $appointment = Appointment::with('patient:id,phone')
            ->where('id', $this->appointmentId)
            ->whereIn('follow_up_status', ['pending', 'failed'])
            ->first();

        if (!$appointment) {
            return;
        }

        // 🔄 نحولها processing (anti-duplicate)
        $appointment->update([
            'follow_up_status' => 'processing'
        ]);

        // ✅ تحقق
        if (!$this->validateFollowUpCanBeSent($appointment)) {
            Log::info('Follow-up skipped', [
                'appointment_id' => $appointment->id,
                'reason' => 'Validation failed'
            ]);

            $appointment->update([
                'follow_up_status' => 'skipped'
            ]);

            return;
        }

        $phone = $appointment->patient?->phone;

        if (!$phone) {
            Log::warning('Follow-up skipped: missing phone', [
                'appointment_id' => $appointment->id
            ]);

            $appointment->update([
                'follow_up_status' => 'failed'
            ]);

            return;
        }

        $message = "How are you feeling after your appointment?";

        try {
            app(\App\Services\Whatsapp\TwilioWhatsappService::class)
                ->send($phone, $message);

            // ✅ نجاح
            $appointment->update([
                ...$this->markFollowUpSent(),
                'follow_up_retry_count' => 0,
                'follow_up_next_retry_at' => null,
            ]);

            Log::info('Follow-up sent successfully', [
                'appointment_id' => $appointment->id,
                'phone' => $phone
            ]);
        } catch (\Exception $e) {
            Log::error('Follow-up failed', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage()
            ]);

            $retryCount = $appointment->follow_up_retry_count + 1;

            if ($retryCount >= 3) {
                $appointment->update([
                    'follow_up_status' => 'failed',
                    'follow_up_retry_count' => $retryCount,
                    'follow_up_next_retry_at' => null,
                ]);
            } else {
                $appointment->update([
                    'follow_up_status' => 'failed',
                    'follow_up_retry_count' => $retryCount,
                    'follow_up_next_retry_at' => now()->addMinutes(5),
                ]);

                Log::warning('Follow-up retry scheduled', [
                    'appointment_id' => $appointment->id,
                    'retry_count' => $retryCount
                ]);
            }
        }
    }
}
