<?php

namespace App\Services;

use App\Services\Alerts\Channels\EmailChannel;
use App\Services\Alerts\Channels\InAppChannel;
use App\Services\Alerts\Channels\WhatsappChannel;

class AlertChannelFactory
{
    public static function make(string $channel)
    {
        return match ($channel) {

            'in_app' =>
            new InAppChannel(),

            'email' =>
            new EmailChannel(),

            'whatsapp' =>
            new WhatsappChannel(),

            default =>
            throw new \InvalidArgumentException(
                "Unknown channel {$channel}"
            ),
        };
    }
}
