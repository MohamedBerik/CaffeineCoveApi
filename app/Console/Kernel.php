<?php

namespace App\Console;

use App\Console\Commands\ResetAccountingForCompany;
use App\Jobs\CheckReminderAlertsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ============================================
        // 1. تذكيرات المواعيد (Reminders)
        // ============================================
        $schedule->command('appointments:send-due-reminders --limit=50')
            ->everyMinute()
            ->withoutOverlapping(5) // ✅ قفل لمدة 5 دقائق لمنع التداخل
            ->onOneServer()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/reminders.log'));

        // ============================================
        // 2. متابعات ما بعد الموعد (Follow-ups)
        // ============================================
        $schedule->command('appointments:send-followups --limit=30')
            ->everyFiveMinutes() // ✅ كل 5 دقائق (مش كل دقيقة - أقل إلحاحًا)
            ->withoutOverlapping(10)
            ->onOneServer()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/followups.log'));

        // ============================================
        // 3. تحديد مواعيد no-show
        // ============================================
        $schedule->command('appointments:mark-no-show')
            ->everyFifteenMinutes() // ✅ كل ربع ساعة (تأخير 30 دقيقة معقول)
            ->withoutOverlapping(15)
            ->onOneServer()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/noshow.log'));

        // ============================================
        // 4. استعادة التذكيرات العالقة
        // ============================================
        $schedule->command('reminders:recover-stuck')
            ->everyTenMinutes()
            ->withoutOverlapping(10)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/recover.log'));

        // ============================================
        // 5. فحص التنبيهات (Reminder Alerts)
        // ============================================
        // ✅ نستخدم الـ Job فقط (يمر على كل الشركات)
        $schedule->job(new CheckReminderAlertsJob())
            ->everyFiveMinutes() // ✅ كل 5 دقائق كافية
            ->withoutOverlapping(10)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/alerts.log'));

        // ============================================
        // 6. BILLING SCHEDULER (NEW)
        // ============================================

        // ✅ تذكير الاشتراكات (كل يوم 8 صباحًا)
        $schedule->command('billing:reminders')
            ->dailyAt('08:00')
            ->withoutOverlapping(30)
            ->onOneServer()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/billing_reminders.log'));

        // ✅ فحص المدفوعات الفاشلة (كل ساعة)
        $schedule->command('billing:check-failed')
            ->hourly()
            ->withoutOverlapping(15)
            ->onOneServer()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/billing_failed.log'));

        // ✅ تنظيف Activity Logs القديمة (كل شهر)
        $schedule->command('cleanup:activity-logs --days=90')
            ->monthly()
            ->withoutOverlapping(60)
            ->onOneServer()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cleanup.log'));

        // ✅ يومياً الساعة 2 صباحاً
        $schedule->command('billing:check-grace-periods')->dailyAt('02:00');
        $schedule->command('billing:check-expired')->dailyAt('02:30');
        $schedule->command('db:backup')->dailyAt('03:00');
    }

    /**
     * The Artisan commands provided by the application.
     */
    protected $commands = [
        ResetAccountingForCompany::class,
        \App\Console\Commands\SystemStats::class,
        \App\Console\Commands\QueueMonitor::class,
        \App\Console\Commands\DatabaseBackup::class,
    ];

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
