<?php

namespace App\Observers;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ActivityLogObserver
{
    public function created(Model $model): void
    {
        if ($this->shouldIgnore($model)) return;

        ActivityLogger::logCreated($model);
    }

    public function updated(Model $model): void
    {
        if ($this->shouldIgnore($model)) return;

        $changes = $model->getChanges();

        // ❗ تجاهل updated_at فقط
        if (count($changes) === 1 && isset($changes['updated_at'])) {
            return;
        }

        // ❗ تجاهل لو مفيش تغييرات حقيقية
        if (empty($changes)) {
            return;
        }

        ActivityLogger::logUpdated($model, $changes);
    }

    public function deleted(Model $model): void
    {
        if ($this->shouldIgnore($model)) return;

        ActivityLogger::logDeleted($model);
    }

    /**
     * ✅ فلترة الموديلات
     */
    private function shouldIgnore(Model $model): bool
    {
        return
            $model instanceof \App\Models\ActivityLog // منع loop
            || $this->isSystemTable($model)
            || !$this->hasCompanyColumn($model);
    }

    /**
     * ❌ تجاهل جداول السيستم
     */
    private function isSystemTable(Model $model): bool
    {
        return in_array($model->getTable(), [
            'activity_logs',
            'sessions',
            'personal_access_tokens',
            'password_reset_tokens',
        ]);
    }

    /**
     * ✅ التأكد إن فيه company_id
     */
    private function hasCompanyColumn(Model $model): bool
    {
        try {
            return Schema::hasColumn($model->getTable(), 'company_id');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
