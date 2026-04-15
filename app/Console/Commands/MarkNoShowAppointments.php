<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use App\Models\Company;
use App\Traits\HandlesAppointmentReminders;
use App\Services\Tenant;

class MarkNoShowAppointments extends Command
{
    use HandlesAppointmentReminders;

    protected $signature = 'appointments:mark-no-show {--company= : Specific company ID}';
    protected $description = 'Mark overdue scheduled appointments as no_show';

    public function handle(): int
    {
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

        $totalMarked = 0;

        // ✅ 2. معالجة كل شركة على حدة
        foreach ($companies as $company) {
            $this->info("Processing company: {$company->name} (ID: {$company->id})");

            // ✅ تعيين Tenant Context للشركة الحالية
            Tenant::setId($company->id);

            $marked = $this->processCompanyAppointments($company->id);
            $totalMarked += $marked;

            // ✅ تنظيف الـ Context بعد كل شركة
            Tenant::reset();
        }

        $this->info("Total marked as no_show: {$totalMarked} appointments across " . $companies->count() . " companies.");

        return self::SUCCESS;
    }

    /**
     * معالجة مواعيد شركة محددة
     */
    private function processCompanyAppointments(int $companyId): int
    {
        $marked = 0;

        // ✅ استخدام chunk لتجنب مشاكل الذاكرة
        Appointment::query()
            ->where('status', 'scheduled')
            ->whereRaw(
                "TIMESTAMP(appointment_date, appointment_time) < ?",
                [now()->subMinutes(30)]
            )
            ->chunk(100, function ($appointments) use (&$marked, $companyId) {
                foreach ($appointments as $appointment) {
                    $appointment->update([
                        'status' => 'no_show',
                        ...$this->markReminderNotNeeded(),
                    ]);

                    $marked++;
                }

                $this->line("  - Marked {$marked} appointments so far...");
            });

        return $marked;
    }
}
