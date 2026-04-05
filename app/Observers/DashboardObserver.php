<?php

use Illuminate\Support\Facades\Cache;

class DashboardObserver
{
    protected function clearDashboardCache($model)
    {
        if (!$model->company_id) return;

        Cache::tags(['dashboard', "company_{$model->company_id}"])->flush();
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
