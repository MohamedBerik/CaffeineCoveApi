<?php

namespace App\Services\Alerts\Channels;

interface AlertChannelInterface
{
    public function send(
        object $recipient,
        string $message,
        array $meta = []
    ): void;
}
