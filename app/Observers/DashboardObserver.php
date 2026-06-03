<?php

namespace App\Observers;

use App\Services\Tenant;
use Illuminate\Support\Facades\Cache;

class DashboardObserver
{
    /**
     * Clear dashboard cache for the company
     */
    protected function clearDashboardCache($model): void
    {
        $companyId = $model->company_id ?? Tenant::id();

        if (!$companyId) {
            return;
        }

        // ✅ Clear all dashboard-related caches
        $cacheKeys = [
            "dashboard_{$companyId}",
            "dashboard_{$companyId}_day",
            "dashboard_{$companyId}_day_normal",
            "dashboard_{$companyId}_day_compare",
            "dashboard_{$companyId}_week",
            "dashboard_{$companyId}_week_normal",
            "dashboard_{$companyId}_week_compare",
            "dashboard_{$companyId}_month",
            "dashboard_{$companyId}_month_normal",
            "dashboard_{$companyId}_month_compare",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // ✅ Clear tenant-aware caches
        Cache::forget(tenant_cache_key('dashboard_stats'));
        Cache::forget(tenant_cache_key('dashboard_kpis'));
        Cache::forget(tenant_cache_key('dashboard_charts'));
    }

    /**
     * Handle the model "created" event.
     */
    public function created($model): void
    {
        $this->clearDashboardCache($model);
        $this->logActivity($model, 'created');
    }

    /**
     * Handle the model "updated" event.
     */
    public function updated($model): void
    {
        // ✅ Only clear cache if relevant fields changed
        if ($this->shouldClearCache($model)) {
            $this->clearDashboardCache($model);
        }

        $this->logActivity($model, 'updated');
    }

    /**
     * Handle the model "deleted" event.
     */
    public function deleted($model): void
    {
        $this->clearDashboardCache($model);
        $this->logActivity($model, 'deleted');
    }

    /**
     * Handle the model "restored" event.
     */
    public function restored($model): void
    {
        $this->clearDashboardCache($model);
    }

    /**
     * Handle the model "force deleted" event.
     */
    public function forceDeleted($model): void
    {
        $this->clearDashboardCache($model);
    }

    /**
     * Check if cache should be cleared based on changed fields
     */
    protected function shouldClearCache($model): bool
    {
        // ✅ Fields that affect dashboard
        $relevantFields = [
            'status',
            'total',
            'amount',
            'applied_amount',
            'appointment_date',
            'appointment_time',
        ];

        foreach ($relevantFields as $field) {
            if ($model->isDirty($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log activity for dashboard-related changes
     */
    protected function logActivity($model, string $action): void
    {
        $companyId = $model->company_id ?? Tenant::id();
        $branchId = $model->branch_id ?? null; // ✅ اجلب branch_id من النموذج

        if (!$companyId) {
            return;
        }

        // ✅ Only log for specific models
        $loggableModels = [
            \App\Models\Appointment::class,
            \App\Models\Invoice::class,
            \App\Models\Payment::class,
            \App\Models\Customer::class,
        ];

        if (!in_array(get_class($model), $loggableModels)) {
            return;
        }

        try {
            \App\Models\ActivityLog::create([
                'company_id' => $companyId,
                'branch_id'  => $branchId, // ✅ أضفناه
                'user_id' => auth()->id(),
                'action' => "dashboard.{$action}",
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
                'properties' => [
                    'model' => class_basename($model),
                    'changes' => $action === 'updated' ? $model->getChanges() : null,
                    'branch_id' => $branchId, // ✅ أضفناه للخصائص
                ],
            ]);
        } catch (\Exception $e) {
            // Fail silently - activity logging should not break the app
        }
    }

    /**
     * Warm up dashboard cache after clearing
     */
    protected function warmUpCache($model): void
    {
        $companyId = $model->company_id ?? Tenant::id();

        if (!$companyId) {
            return;
        }

        // ✅ Schedule cache warm-up for next request
        // This is optional - can be implemented if needed
    }
}
