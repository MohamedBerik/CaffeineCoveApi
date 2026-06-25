<?php

namespace App\Services\Alerts\Channels;

use App\Events\AlertCreated;
use App\Models\SystemAlert;

class InAppChannel implements AlertChannelInterface
{
    public function send(
        object $recipient,
        string $message,
        array $meta = []
    ): void {

        $alert = SystemAlert::create([
            'company_id'   => $recipient->company_id,
            'branch_id'    => $recipient->branch_id ?? null,
            'user_id'      => $recipient->id,
            'code'         => $meta['code'] ?? null,
            'type'         => $meta['type'] ?? SystemAlert::TYPE_SYSTEM,
            'priority'     => $meta['priority'] ?? SystemAlert::PRIORITY_MEDIUM,
            'message'      => $message,
            'meta'         => $meta,
            'triggered_at' => now(),
        ]);

        event(new AlertCreated($alert));
    }
}
