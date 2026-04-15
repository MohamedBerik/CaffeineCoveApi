<?php

use App\Models\ActivityLog;
use App\Services\Tenant;

if (!function_exists('activity')) {
    function activity(string $action, $model, ?array $properties = null, ?int $userId = null): ?ActivityLog
    {
        $companyId = $model->company_id ?? Tenant::id();

        if (!$companyId) {
            return null;
        }

        $userId = $userId ?? auth()->id();

        return ActivityLog::create([
            'company_id'   => $companyId,
            'user_id'      => $userId,
            'action'       => $action,
            'subject_type' => get_class($model),
            'subject_id'   => $model->id,
            'properties'   => $properties,
        ]);
    }
}
