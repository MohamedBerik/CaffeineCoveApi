<?php

namespace App\Services\Alerts\Channels;

use Illuminate\Support\Facades\Log;

class EmailChannel implements AlertChannelInterface
{
    public function send(
        object $recipient,
        string $message,
        array $meta = []
    ): void {

        Log::info('Email Alert', [
            'user_id' => $recipient->id,
            'email'   => $recipient->email ?? 'no-email',
            'message' => $message,
        ]);

        // TODO: تنفيذ إرسال البريد الإلكتروني فعليًا
    }
}
