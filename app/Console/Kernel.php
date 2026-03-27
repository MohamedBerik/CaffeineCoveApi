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
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('appointments:send-followups')
            ->everyMinute()
            ->withoutOverlapping();
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
