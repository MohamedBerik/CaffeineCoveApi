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
    protected $signature = 'appointments:send-followups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send follow-up messages to patients after completed appointments';

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
        $limit = 50;

        $appointments = Appointment::query()
            ->where('status', 'completed')
            ->where('follow_up_status', 'pending')
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', now())
            ->orderBy('follow_up_at', 'asc')
            ->limit($limit)
            ->get(['id']);

        if ($appointments->isEmpty()) {
            $this->info('No follow-ups due.');
            return self::SUCCESS;
        }

        foreach ($appointments as $appointment) {
            SendAppointmentFollowUpJob::dispatch($appointment->id);
        }

        $this->info("Dispatched {$appointments->count()} follow-up job(s).");

        return self::SUCCESS;
    }
}
