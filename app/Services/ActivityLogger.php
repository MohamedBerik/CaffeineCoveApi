<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogger
{
    public static function log(Request $request, string $action, $subject, array $properties = []): void
    {
        $user = $request->user();

        ActivityLog::create([
            'company_id'   => $user?->company_id,
            'user_id'      => $user?->id,
            'action'       => $action,
            'subject_type' => get_class($subject),
            'subject_id'   => $subject->id,
            'properties'   => $properties,
        ]);
    }
}
