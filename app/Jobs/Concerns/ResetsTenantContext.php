<?php
// app/Jobs/Concerns/ResetsTenantContext.php

namespace App\Jobs\Concerns;

use App\Services\Tenant;

trait ResetsTenantContext
{
    public function handle()
    {
        try {
            $this->process();
        } finally {
            // ✅ Reset بعد كل Job
            Tenant::reset();
        }
    }

    abstract protected function process();
}
