<?php

namespace App\Jobs\Concerns;

use Illuminate\Support\Facades\Log;

trait HandlesFailure
{
    /**
     * التعامل مع فشل الـ Job بعد استنفاذ كل المحاولات
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Job failed permanently', [
            'job' => static::class,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'attempts' => $this->attempts(),
            'payload' => $this->payload ?? 'N/A',
        ]);

        // ✅ إرسال إشعار للإدارة (اختياري)
        if (app()->environment('production')) {
            // Notification::route('mail', 'admin@caffeinecove.com')
            //     ->notify(new JobFailedNotification(static::class, $exception));
        }
    }
}
