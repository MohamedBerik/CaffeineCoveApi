<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Company;
use App\Services\Tenant;
use Illuminate\Console\Command;
use App\Jobs\SendAppointmentFollowUpJob;

class SendDueFollowUps extends Command
{
    protected $signature = 'appointments:send-followups
                            {--limit=50 : Max follow-ups per company}
                            {--company= : Specific company ID}';

    protected $description = 'Send follow-up messages to patients after completed appointments (with retry support)';

    public function handle(): int
    {
        $limit = max((int) $this->option('limit'), 1);

        // ✅ 1. تحديد الشركات المستهدفة
        if ($companyId = $this->option('company')) {
            $companies = Company::where('id', $companyId)->get();
        } else {
            $companies = Company::whereIn('status', ['active', 'trial'])->get();
        }

        if ($companies->isEmpty()) {
            $this->info('No companies found to process.');
            return self::SUCCESS;
        }

        $totalDispatched = 0;

        // ✅ 2. معالجة كل شركة على حدة
        foreach ($companies as $company) {
            $this->line("Processing company: {$company->name} (ID: {$company->id})");

            // ✅ تعيين Tenant Context للشركة الحالية
            Tenant::setId($company->id);

            $dispatched = $this->processCompanyFollowUps($company->id, $limit);
            $totalDispatched += $dispatched;

            $this->line("  - Dispatched {$dispatched} follow-up jobs.");

            // ✅ تنظيف الـ Context
            Tenant::reset();
        }

        $this->info("Total dispatched: {$totalDispatched} follow-up jobs across " . $companies->count() . " companies.");

        return self::SUCCESS;
    }

    /**
     * معالجة متابعات شركة محددة
     */
    private function processCompanyFollowUps(int $companyId, int $limit): int
    {
        $appointments = Appointment::query()
            ->where('status', 'completed')
            ->whereIn('follow_up_state', ['pending', 'retrying'])
            ->where(function ($q) {
                // 🟢 pending (first attempt)
                $q->where(function ($q) {
                    $q->where('follow_up_state', 'pending')
                        ->whereNotNull('follow_up_at')
                        ->where('follow_up_at', '<=', now());
                })
                    // 🔁 retry
                    ->orWhere(function ($q) {
                        $q->where('follow_up_state', 'retrying')
                            ->where('follow_up_retry_count', '<', 3)
                            ->whereNotNull('follow_up_next_retry_at')
                            ->where('follow_up_next_retry_at', '<=', now());
                    });
            })
            // 🧠 priority: pending أولاً، ثم retrying
            ->orderByRaw("
                CASE
                    WHEN follow_up_state = 'pending' THEN 1
                    WHEN follow_up_state = 'retrying' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('follow_up_at', 'asc')
            ->orderBy('follow_up_next_retry_at', 'asc')
            ->limit($limit)
            ->get(['id']);

        if ($appointments->isEmpty()) {
            return 0;
        }

        $dispatched = 0;
        foreach ($appointments as $index => $appointment) {
            SendAppointmentFollowUpJob::dispatch($appointment->id)
                ->delay(now()->addSeconds($index * 2)); // ✅ توزيع الحمل على الـ Queue

            $dispatched++;
        }

        return $dispatched;
    }
}
