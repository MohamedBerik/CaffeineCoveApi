<?php

namespace App\Services\Whatsapp;

use Twilio\Rest\Client;

class TwilioWhatsappService
{
    public function send(string $to, string $message): array
    {
        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $from = config('services.twilio.whatsapp_from');

        $client = new Client($sid, $token);

        $response = $client->messages->create(
            'whatsapp:' . $this->normalizePhone($to),
            [
                'from' => $from,
                'body' => $message,
            ]
        );

        return [
            'sid' => $response->sid ?? null,
            'status' => $response->status ?? null,
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        if (str_starts_with($phone, 'whatsapp:')) {
            $phone = substr($phone, 9);
        }

        return $phone;
    }
}
