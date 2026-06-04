<?php

namespace App\Events;

use App\Models\ActivityLog;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityLogCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public ActivityLog $log;

    public function __construct(ActivityLog $log)
    {
        $this->log = $log;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'company.' . $this->log->company_id . '.activity-logs'
            )
        ];
    }

    public function broadcastAs(): string
    {
        return 'activity-log.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->log->id,
            'company_id' => $this->log->company_id,
            'branch_id' => $this->log->branch_id,
            'user_id' => $this->log->user_id,
            'action' => $this->log->action,
            'subject_type' => $this->log->subject_type,
            'subject_id' => $this->log->subject_id,
            'properties' => $this->log->properties,
            'created_at' => $this->log->created_at?->toISOString(),
            'user_name' => $this->log->user?->name,
            'user_email' => $this->log->user?->email,
        ];
    }
}
