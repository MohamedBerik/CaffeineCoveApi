<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminderJob;
use App\Models\Appointment;
use App\Models\Company;
use App\Services\Tenant;
use Illuminate\Console\Command;

class SendDueAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-due-reminders
                            {--limit=100 : Max appointments per company}
                            {--company= : Specific company ID}';

    protected $description = 'Dispatch reminder jobs for appointments whose reminders are due';

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

            $dispatched = $this->processCompanyAppointments($company->id, $limit);
            $totalDispatched += $dispatched;

            $this->line("  - Dispatched {$dispatched} reminder jobs.");

            // ✅ تنظيف الـ Context
            Tenant::reset();
        }

        $this->info("Total dispatched: {$totalDispatched} reminder jobs across " . $companies->count() . " companies.");

        return self::SUCCESS;
    }

    /**
     * معالجة مواعيد شركة محددة
     */
    private function processCompanyAppointments(int $companyId, int $limit): int
    {
        $dispatched = 0;

        // ✅ جلب المواعيد المستحقة
        $appointments = Appointment::query()
            ->where('status', 'scheduled')
            ->where('reminder_status', 'pending')
            ->whereNotNull('next_reminder_at')
            ->where('next_reminder_at', '<=', now())
            ->orderBy('next_reminder_at') // ✅ الأقدم أولاً
            ->limit($limit)
            ->get();

        if ($appointments->isEmpty()) {
            return 0;
        }

        // ✅ تحديث الحالة لـ processing بشكل فردي (بدون Transaction)
        foreach ($appointments as $appointment) {
            // قفل الصف لمنع التعديل المتزامن
            $updated = Appointment::where('id', $appointment->id)
                ->where('reminder_status', 'pending') // ✅ شرط إضافي للأمان
                ->update(['reminder_status' => 'processing']);

            if ($updated) {
                // ✅ إرسال الـ Job مع تأخير بسيط بين كل Job لتجنب الضغط
                SendAppointmentReminderJob::dispatch($appointment->id)
                    ->delay(now()->addSeconds($dispatched * 2)); // 2 ثواني بين كل Job

                $dispatched++;
            }
        }

        return $dispatched;
    }
}
