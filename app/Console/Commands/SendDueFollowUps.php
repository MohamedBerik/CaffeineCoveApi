<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;
use App\Jobs\SendAppointmentFollowUpJob;

class SendDueFollowUps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:name';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $appointments = Appointment::query()
            ->where('status', 'completed')
            ->where('follow_up_status', 'pending')
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', now())
            ->limit(50)
            ->pluck('id');

        foreach ($appointments as $id) {
            SendAppointmentFollowUpJob::dispatch($id);
        }

        return self::SUCCESS;
    }
}
