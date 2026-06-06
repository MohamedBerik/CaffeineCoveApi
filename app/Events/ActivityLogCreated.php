<?php

namespace App\Events;

use App\Models\ActivityLog;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ActivityLogCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public $log;

    public function __construct(ActivityLog $log)
    {
        $this->log = $log;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('saas.activity-logs'),

            new PrivateChannel(
                'company.' . $this->log->company_id . '.activity-logs'
            ),
        ];

        if ($this->log->branch_id) {
            $channels[] = new PrivateChannel(
                'company.' . $this->log->company_id .
                    '.branch.' . $this->log->branch_id .
                    '.activity-logs'
            );
        }

        return $channels;
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
            'action' => $this->log->action,
            'category' => $this->log->category,
            'user_id' => $this->log->user_id,
            'user_name' => $this->log->user?->name ?? 'System',
            'subject_type' => $this->log->subject_type,
            'subject_id' => $this->log->subject_id,
            'branch_id' => $this->log->branch_id,
            'properties' => $this->log->properties,
            'created_at' => $this->log->created_at?->toISOString(),
        ];
    }
}
