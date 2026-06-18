<?php

namespace App\Services;

use App\Events\UserNotificationCreated;

class UserNotificationService
{
    public static function send(
        int $userId,
        string $type,
        string $title,
        ?string $message = null,
        array $data = []
    ): void {
        event(
            new UserNotificationCreated(
                $userId,
                [
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'data' => $data,
                    'created_at' => now()->toISOString(),
                ]
            )
        );
    }
}
