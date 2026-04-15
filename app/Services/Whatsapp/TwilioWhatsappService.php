<?php

namespace App\Services\Whatsapp;

use App\Services\ActivityLogger;
use App\Services\Tenant;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioWhatsappService
{
    /**
     * Template IDs for different message types
     */
    const TEMPLATE_APPOINTMENT_REMINDER = 'HXb5b62575e6e4ff6129ad7c8efe1f983e';
    const TEMPLATE_FOLLOW_UP = 'follow_up_template_id';
    const TEMPLATE_CONFIRMATION = 'confirmation_template_id';

    /**
     * Send WhatsApp message using template
     */
    public function send(string $to, string $message, ?string $templateId = null, array $variables = []): array
    {
        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $from = config('services.twilio.whatsapp_from');

        $from = preg_replace('/^whatsapp:/', '', $from);

        if (!$sid || !$token || !$from) {
            throw new \Exception('Twilio configuration is missing. Check your .env file.');
        }

        $client = new Client($sid, $token);

        // Use provided template or fallback to simple message
        if ($templateId) {
            return $this->sendTemplate($client, $to, $from, $templateId, $variables);
        }

        return $this->sendSimpleMessage($client, $to, $from, $message);
    }

    /**
     * Send template-based message
     */
    protected function sendTemplate(Client $client, string $to, string $from, string $templateId, array $variables): array
    {
        try {
            $response = $client->messages->create(
                'whatsapp:' . $this->normalizePhone($to),
                [
                    'from' => 'whatsapp:' . $from,
                    'contentSid' => $templateId,
                    'contentVariables' => json_encode($variables),
                ]
            );

            $this->logSuccess($to, $response, [
                'template_id' => $templateId,
                'variables' => $variables,
            ]);

            return [
                'success' => true,
                'sid' => $response->sid,
                'status' => $response->status,
            ];
        } catch (\Exception $e) {
            $this->logError($to, $e, [
                'template_id' => $templateId,
                'variables' => $variables,
            ]);
            throw $e;
        }
    }

    /**
     * Send simple text message (fallback)
     */
    protected function sendSimpleMessage(Client $client, string $to, string $from, string $message): array
    {
        try {
            $response = $client->messages->create(
                'whatsapp:' . $this->normalizePhone($to),
                [
                    'from' => 'whatsapp:' . $from,
                    'body' => $message,
                ]
            );

            $this->logSuccess($to, $response, ['message' => $message]);

            return [
                'success' => true,
                'sid' => $response->sid,
                'status' => $response->status,
            ];
        } catch (\Exception $e) {
            $this->logError($to, $e, ['message' => $message]);
            throw $e;
        }
    }

    /**
     * Send appointment reminder
     */
    public function sendAppointmentReminder(
        string $to,
        string $appointmentDate,
        string $appointmentTime,
        ?string $doctorName = null
    ): array {
        $variables = [
            '1' => $appointmentDate,
            '2' => $appointmentTime,
        ];

        if ($doctorName) {
            $variables['3'] = $doctorName;
        }

        return $this->send(
            $to,
            '',
            self::TEMPLATE_APPOINTMENT_REMINDER,
            $variables
        );
    }

    /**
     * Send follow-up message
     */
    public function sendFollowUp(string $to, string $patientName = ''): array
    {
        $variables = [];

        if ($patientName) {
            $variables['1'] = $patientName;
        }

        return $this->send(
            $to,
            '',
            self::TEMPLATE_FOLLOW_UP,
            $variables
        );
    }

    /**
     * Normalize phone number to international format
     */
    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        // Remove whatsapp: prefix if present
        if (str_starts_with($phone, 'whatsapp:')) {
            $phone = substr($phone, 9);
        }

        // Convert Egyptian numbers starting with 0 to +20
        if (str_starts_with($phone, '0')) {
            $phone = '+2' . $phone;
        }

        // Fix double country code (+2120... → +20...)
        if (str_starts_with($phone, '+212')) {
            $phone = '+2' . substr($phone, 4);
        }

        // Ensure +2 prefix for Egyptian numbers
        if (str_starts_with($phone, '2') && !str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Validate phone number format
     */
    public function validatePhone(string $phone): bool
    {
        $phone = $this->normalizePhone($phone);

        // Egyptian format: +201xxxxxxxxx
        return (bool) preg_match('/^\+20[0-9]{10}$/', $phone);
    }

    /**
     * Log successful message
     */
    private function logSuccess(string $to, $response, array $context = []): void
    {
        $logContext = array_merge([
            'company_id' => Tenant::id(),
            'to' => $to,
            'sid' => $response->sid ?? null,
            'status' => $response->status ?? null,
        ], $context);

        Log::info('WhatsApp message sent successfully', $logContext);

        // ✅ Log to activity
        if (Tenant::hasTenant()) {
            ActivityLogger::logAction(
                'whatsapp.sent',
                'whatsapp',
                null,
                $logContext
            );
        }
    }

    /**
     * Log failed message
     */
    private function logError(string $to, \Exception $e, array $context = []): void
    {
        $logContext = array_merge([
            'company_id' => Tenant::id(),
            'to' => $to,
            'error' => $e->getMessage(),
        ], $context);

        Log::error('WhatsApp message failed', $logContext);

        // ✅ Log to activity
        if (Tenant::hasTenant()) {
            ActivityLogger::logError(
                'WhatsApp message failed: ' . $e->getMessage(),
                $logContext
            );
        }
    }

    /**
     * Check if WhatsApp service is configured
     */
    public function isConfigured(): bool
    {
        return config('services.twilio.account_sid')
            && config('services.twilio.auth_token')
            && config('services.twilio.whatsapp_from');
    }

    /**
     * Get service status
     */
    public function getStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'from_number' => config('services.twilio.whatsapp_from'),
            'environment' => app()->environment(),
        ];
    }
}
