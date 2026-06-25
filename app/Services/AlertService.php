<?php

namespace App\Services;

use App\Events\AlertCreated;
use App\Models\SystemAlert;
use Illuminate\Support\Collection;

class AlertService
{
    public static function send(
        Collection $recipients,
        string $message,
        string $type = SystemAlert::TYPE_SYSTEM,
        string $priority = SystemAlert::PRIORITY_MEDIUM,
        array $meta = [],
        ?string $code = null,
        ?int $companyId = null,
        ?int $branchId = null
    ): void {
        foreach ($recipients as $user) {

            $alert = SystemAlert::create([
                'company_id'   => $companyId ?? $user->company_id,
                'branch_id'    => $branchId ?? $user->branch_id,
                'user_id'      => $user->id,
                'code'         => $code,
                'type'         => $type,
                'priority'     => $priority,
                'message'      => $message,
                'meta'         => $meta,
                'triggered_at' => now(),
            ]);

            event(new AlertCreated($alert));
        }
    }

    public static function sendCode(
        Collection $recipients,
        string $code,
        array $templateData = [],
        array $meta = [],
        ?int $companyId = null,
        ?int $branchId = null
    ): void {
        $definition = AlertDefinitionService::definition($code);

        $message = AlertTemplateService::render(
            $code,
            $templateData
        );

        // ✅ استخراج القنوات من التعريف (افتراضيًا in_app)
        $channels = $definition['channels'] ?? ['in_app'];

        foreach ($channels as $channel) {
            $driver = AlertChannelFactory::make($channel);

            foreach ($recipients as $recipient) {
                $driver->send(
                    $recipient,
                    $message,
                    [
                        'code'     => $code,
                        'type'     => $definition['type'],
                        'priority' => $definition['priority'],
                        ...$meta,
                    ]
                );
            }
        }
    }
}
