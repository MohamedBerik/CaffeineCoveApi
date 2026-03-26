<?php

use App\Models\Appointment;
use App\Traits\HandlesAppointmentFollowUps;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendAppointmentFollowUpJob implements ShouldQueue
{
    use HandlesAppointmentFollowUps;

    public function __construct(public int $appointmentId) {}

    public function handle(): void
    {
        $appointment = Appointment::with('patient:id,phone')
            ->find($this->appointmentId);

        if (!$appointment) return;

        if (!$this->validateFollowUpCanBeSent($appointment)) {
            Log::info('Follow-up skipped', ['id' => $appointment->id]);
            return;
        }

        $phone = $appointment->patient?->phone;
        if (!$phone) return;

        $message = "How are you feeling after your appointment?";

        try {
            app(\App\Services\Whatsapp\TwilioWhatsappService::class)
                ->send($phone, $message);

            $appointment->update($this->markFollowUpSent());
        } catch (\Exception $e) {
            Log::error('Follow-up failed', [
                'id' => $appointment->id,
                'error' => $e->getMessage()
            ]);

            $appointment->update([
                'follow_up_status' => 'failed'
            ]);
        }
    }
}
