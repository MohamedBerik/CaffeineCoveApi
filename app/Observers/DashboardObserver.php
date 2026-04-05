<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;

class DashboardObserver
{
    protected function clearDashboardCache($model)
    {
        if (!$model->company_id) return;

        Cache::forget("dashboard_{$model->company_id}");
    }

    public function created($model)
    {
        $this->clearDashboardCache($model);
    }

    public function updated($model)
    {
        $this->clearDashboardCache($model);
    }

    public function deleted($model)
    {
        $this->clearDashboardCache($model);
    }
}
