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

        // إزالة "whatsapp:" من from إذا كانت موجودة
        $from = preg_replace('/^whatsapp:/', '', $from);

        $client = new Client($sid, $token);

        $response = $client->messages->create(
            'whatsapp:' . $this->normalizePhone($to),
            [
                'from' => 'whatsapp:' . $from,
                'body' => $message,
            ]
        );

        return [
            'sid' => $response->sid ?? null,
            'status' => $response->status ?? null,
        ];
    }

    // public function send($to, $message)
    // {
    //     Log::info('Mock WhatsApp', compact('to', 'message'));

    //     return [
    //         'sid' => 'mock',
    //         'status' => 'sent'
    //     ];
    // }

    // public function send($phone, $message)
    // {
    //     throw new \Exception('Forced failure for testing');
    // }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        // remove whatsapp:
        if (str_starts_with($phone, 'whatsapp:')) {
            $phone = substr($phone, 9);
        }

        // لو مصري وبدأ بـ 0 → حوله لـ +20
        if (str_starts_with($phone, '0')) {
            $phone = '+2' . substr($phone, 1);
        }

        return $phone;
    }
}
