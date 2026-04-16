<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Procedure;
use App\Models\TreatmentPlan;
use App\Services\Tenant;
use Illuminate\Database\Seeder;

class DevelopmentDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating development data...');

        $companies = Company::whereIn('status', ['active', 'trial'])->get();

        foreach ($companies as $company) {
            Tenant::forCompany($company->id, function () use ($company) {
                $this->seedCompanyData($company);
            });
        }
    }

    /**
     * Seed data for a specific company.
     */
    private function seedCompanyData(Company $company): void
    {
        $this->command->line("  Seeding data for: {$company->name}");

        // 1. إنشاء إجراءات (Procedures)
        Procedure::factory()->count(8)->create(['company_id' => $company->id]);

        // 2. إنشاء مرضى (Customers)
        $customers = Customer::factory()->count(20)->create(['company_id' => $company->id]);

        // 3. إنشاء دكاترة (Doctors)
        $doctors = Doctor::factory()->count(3)->create(['company_id' => $company->id]);

        // 4. إنشاء مواعيد (Appointments)
        foreach ($customers->random(15) as $customer) {
            Appointment::factory()->create([
                'company_id' => $company->id,
                'patient_id' => $customer->id,
                'doctor_id' => $doctors->random()->id,
            ]);
        }

        // 5. إنشاء فواتير (Invoices)
        Invoice::factory()->count(10)->create(['company_id' => $company->id]);

        // 6. إنشاء خطط علاج (Treatment Plans)
        TreatmentPlan::factory()->count(3)->create(['company_id' => $company->id]);

        $this->command->line("    ✅ 8 procedures, 20 customers, 3 doctors, 15 appointments, 10 invoices, 3 treatment plans");
    }
}
