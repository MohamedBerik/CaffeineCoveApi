<?php

namespace App\Services;

use App\Models\Notification;
use App\Events\UserNotificationCreated;

class NotificationService
{
    public static function send(
        int $userId,
        string $title,
        string $message,
        array $data = [],
        string $type = 'info',
        string $priority = 'medium',
        ?int $companyId = null,
        ?int $branchId = null
    ) {
        $notification = Notification::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'priority' => $priority,
            'data' => $data,
        ]);

        event(new UserNotificationCreated(
            $userId,
            [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'priority' => $notification->priority,
                'data' => $notification->data,
                'created_at' => $notification->created_at,
            ]
        ));

        return $notification;
    }
}
