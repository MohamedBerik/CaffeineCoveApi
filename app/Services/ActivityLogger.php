<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;

class ActivityLogger
{
    public static function log(
        int $companyId,
        ?Authenticatable $user,
        string $action,
        string $subjectType,
        int $subjectId,
        array $properties = []
    ): ActivityLog {
        return ActivityLog::create([
            'company_id'   => $companyId,
            'user_id'      => $user?->id,
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'properties'   => $properties, // ✅ array → JSON
        ]);
    }
}
