<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RecoverStuckReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:recover-stuck';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recover stuck reminders that have been processing for too long';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Recovering stuck reminders...');

        $updated = Appointment::query()
            ->where('reminder_status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update([
                'reminder_status' => 'pending',
                'next_reminder_at' => now()->addMinute(),
            ]);

        $this->info("Recovered {$updated} stuck reminders.");

        return Command::SUCCESS;
    }
}
