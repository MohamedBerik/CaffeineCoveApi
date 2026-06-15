<?php

namespace App\Listeners;

use App\Events\SystemExceptionOccurred;
use App\Models\ActivityLog;

class LogSystemException
{
    public function handle(SystemExceptionOccurred $event)
    {
        ActivityLog::create([
            'company_id' => null,
            'branch_id' => null,
            'user_id' => null,
            'action' => 'system.exception',
            'subject_type' => 'System',
            'subject_id' => 0,
            'properties' => [
                'message' => $event->message,
                'exception' => $event->exception,
                'file' => $event->file,
                'line' => $event->line,
            ]
        ]);
    }
}
