<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('appointments:send-due-reminders')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/schedule.log'))
            ->runInBackground();

        $schedule->command('appointments:send-followups')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/schedule.log'))
            ->runInBackground();

        $schedule->command('appointments:mark-no-show')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/schedule.log'))
            ->runInBackground();
    }
    protected $commands = [
        \App\Console\Commands\ResetAccountingForCompany::class,
    ];

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
