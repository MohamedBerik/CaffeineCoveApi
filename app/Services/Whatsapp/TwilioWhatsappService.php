<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioWhatsappService
{
    public function send(string $to, string $message): array
    {
        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $from = config('services.twilio.whatsapp_from');

        $from = preg_replace('/^whatsapp:/', '', $from);

        if (!$sid || !$token || !$from) {
            throw new \Exception('Twilio config missing');
        }

        $client = new Client($sid, $token);

        $response = $client->messages->create(
            'whatsapp:' . $this->normalizePhone($to),
            [
                'from' => 'whatsapp:' . $from,
                'body' => $message,
            ]
        );

        Log::info('WhatsApp sent', [
            'to' => $to,
            'sid' => $response->sid ?? null,
            'status' => $response->status ?? null,
        ]);

        return [
            'sid' => $response->sid ?? null,
            'status' => $response->status ?? null,
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        // remove whatsapp:
        if (str_starts_with($phone, 'whatsapp:')) {
            $phone = substr($phone, 9);
        }

        // لو مصري وبدأ بـ 0 → حوله لـ +20
        if (str_starts_with($phone, '0')) {
            $phone = '+20' . substr($phone, 1);  // +20 مش +2
        }

        // لو الرقم بيبدأ بـ +21 (غلط) صححه
        if (str_starts_with($phone, '+21')) {
            $phone = '+20' . substr($phone, 3);
        }

        return $phone;
    }
}
