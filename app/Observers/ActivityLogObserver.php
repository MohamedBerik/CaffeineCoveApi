<?php

namespace App\Observers;

use App\Services\ActivityLogger;

class ActivityLogObserver
{
    public function created($model): void
    {
        if ($this->shouldIgnore($model)) {
            return;
        }

        ActivityLogger::logCreated($model);
    }

    public function updated($model): void
    {
        if ($this->shouldIgnore($model)) {
            return;
        }

        ActivityLogger::logUpdated($model);
    }

    public function deleted($model): void
    {
        if ($this->shouldIgnore($model)) {
            return;
        }

        ActivityLogger::logDeleted($model);
    }

    private function shouldIgnore($model): bool
    {
        // منع التكرار اللانهائي
        return $model instanceof \App\Models\ActivityLog
            || !method_exists($model, 'getAttributes');
    }
}
