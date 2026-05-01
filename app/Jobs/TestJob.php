<?php

namespace App\Jobs;

use App\Services\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tenantId;

    public function __construct($tenantId)
    {
        $this->tenantId = $tenantId;
    }

    public function handle()
    {
        // ✅ تهيئة سياق المستأجر (إذا لزم الأمر)
        if ($this->tenantId) {
            Tenant::setId($this->tenantId);
        }

        Log::info('Test job executed successfully!', ['tenant_id' => $this->tenantId]);
    }
}
