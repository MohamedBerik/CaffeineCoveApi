<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\ReminderAlertService;
use App\Jobs\Concerns\ResetsTenantContext;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckReminderAlertsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use ResetsTenantContext;

    /**
     * عدد مرات إعادة المحاولة في حالة الفشل
     */
    public $tries = 3;

    /**
     * أقصى مدة للتنفيذ (بالثواني)
     */
    public $timeout = 600;

    /**
     * التأخير بين المحاولات الفاشلة (بالثواني)
     */
    public function backoff(): array
    {
        return [60, 300, 600]; // 1 دقيقة -> 5 دقائق -> 10 دقائق
    }

    /**
     * مفتاح فريد لمنع تشغيل المهمة أكثر من مرة في نفس الوقت
     */
    public function uniqueId(): string
    {
        return 'check_reminder_alerts';
    }

    /**
     * مدة الاحتفاظ بالقفل (Lock) لمنع التداخل
     */
    public function uniqueFor(): int
    {
        return 900; // 15 دقيقة
    }

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $this->process();
    }

    protected function process(): void
    {
        // ✅ استخدام cursor بدلاً من get لتوفير الذاكرة (Streaming)
        $companies = Company::whereIn('status', ['active', 'trial'])
            ->select(['id', 'name', 'status'])
            ->cursor();

        $totalProcessed = 0;
        $totalFailed = 0;

        Log::info('Starting reminder alerts check');

        foreach ($companies as $company) {
            try {
                Tenant::forCompany($company->id, function () use ($company) {
                    Log::debug('Checking reminders for company', [
                        'company_id' => $company->id,
                        'company_name' => $company->name
                    ]);

                    app(ReminderAlertService::class)
                        ->checkAndTriggerAlerts($company->id);
                });

                $totalProcessed++;

                // ✅ راحة بسيطة جدًا عشان ما نضغطش على السيرفر (اختياري)
                if ($totalProcessed % 10 === 0) {
                    usleep(100000); // 0.1 ثانية راحة
                }
            } catch (\Exception $e) {
                $totalFailed++;

                Log::error('Failed to check reminders for company', [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString() // ✅ إضافة trace للتتبع
                ]);

                // ✅ لا نوقف الحلقة (continue)
                continue;
            }
        }

        Log::info('Completed reminder alerts check', [
            'total_processed' => $totalProcessed,
            'total_failed' => $totalFailed
        ]);
    }

    /**
     * التعامل مع فشل المهمة بشكل كامل (بعد استنفاذ المحاولات)
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('CheckReminderAlertsJob failed completely', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // يمكن إرسال إشعار للمشرفين هنا
    }
}
