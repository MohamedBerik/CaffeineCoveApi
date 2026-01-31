<?php

use App\Models\ActivityLog;

if (! function_exists('activity')) {

    function activity($action, $model, $properties = null)
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => get_class($model),
            'subject_id' => $model->id,
            'properties' => $properties
        ]);
    }
}
