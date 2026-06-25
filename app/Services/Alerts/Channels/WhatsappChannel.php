<?php

namespace App\Services\Alerts\Channels;

use Illuminate\Support\Facades\Log;

class WhatsappChannel implements AlertChannelInterface
{
    public function send(
        object $recipient,
        string $message,
        array $meta = []
    ): void {

        Log::info('Whatsapp Alert', [
            'user_id' => $recipient->id,
            'message' => $message,
        ]);

        // TODO: تنفيذ إرسال WhatsApp فعليًا
    }
}
