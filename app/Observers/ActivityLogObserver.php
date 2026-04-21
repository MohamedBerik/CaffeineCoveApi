<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use App\Services\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ActivityLogObserver
{
    protected $manualLoggingModels = [
        \App\Models\Appointment::class,
        \App\Models\Invoice::class,
        \App\Models\TreatmentPlan::class,
        \App\Models\Order::class,
        \App\Models\PurchaseOrder::class,
    ];

    public function created(Model $model)
    {
        if (in_array(get_class($model), $this->manualLoggingModels)) {
            return;
        }

        ActivityLog::create([
            'company_id' => $model->company_id ?? Tenant::id(),
            'user_id' => auth()->id(),
            'action' => strtolower(class_basename($model)) . '.created', // ✅ product.created
            'subject_type' => get_class($model),
            'subject_id' => $model->id,
            'properties' => [
                'attributes' => $model->toArray(),
                'meta' => $this->getRequestMeta(),
            ],
        ]);
    }

    public function updated(Model $model)
    {
        if (in_array(get_class($model), $this->manualLoggingModels)) {
            return;
        }

        // ✅ احصل على التغييرات بدون updated_at
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        // ✅ لو مفيش تغييرات حقيقية، متسجلش
        if (empty($changes)) {
            return;
        }

        ActivityLog::create([
            'company_id' => $model->company_id ?? Tenant::id(),
            'user_id' => auth()->id(),
            'action' => strtolower(class_basename($model)) . '.updated', // ✅ product.updated
            'subject_type' => get_class($model),
            'subject_id' => $model->id,
            'properties' => [
                'old' => array_intersect_key($model->getOriginal(), $changes),
                'new' => $changes,
                'changed_fields' => array_keys($changes),
                'meta' => $this->getRequestMeta(),
            ],
        ]);
    }

    public function deleted(Model $model)
    {
        if (in_array(get_class($model), $this->manualLoggingModels)) {
            return;
        }

        ActivityLog::create([
            'company_id' => $model->company_id ?? Tenant::id(),
            'user_id' => auth()->id(),
            'action' => strtolower(class_basename($model)) . '.deleted', // ✅ product.deleted
            'subject_type' => get_class($model),
            'subject_id' => $model->id,
            'properties' => [
                'attributes' => $model->toArray(),
                'meta' => $this->getRequestMeta(),
            ],
        ]);
    }

    /**
     * ✅ Get request meta information
     */
    private function getRequestMeta(): array
    {
        $request = request();

        return [
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
        ];
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
