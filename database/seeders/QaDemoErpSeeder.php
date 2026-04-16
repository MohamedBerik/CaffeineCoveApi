<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DentalRecord;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Procedure;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class QaDemoErpSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🧪 Creating QA demo data...');

        $company = Company::query()->first();

        if (!$company) {
            $this->command->error('No company found. Run CompanySeeder first.');
            return;
        }

        // ✅ تشغيل كل حاجة في Tenant Context
        Tenant::forCompany($company->id, function () use ($company) {
            $this->seedQaData($company);
        });

        $this->displayQaSummary($company);
    }

    /**
     * Seed QA data for the company.
     */
    private function seedQaData(Company $company): void
    {
        // 1. Clean old QA data
        $this->cleanOldQaData($company->id);

        // 2. Get or create admin
        $admin = $this->getOrCreateAdmin($company);

        // 3. Create doctors
        $doctors = $this->createQaDoctors($company->id);

        // 4. Create patients
        $patients = $this->createQaPatients($company->id);

        // 5. Create procedures
        $procedures = $this->createQaProcedures($company->id);

        // 6. Create appointments
        $appointments = $this->createQaAppointments($company->id, $patients, $doctors, $admin->id);

        // 7. Create dental records
        $this->createQaDentalRecords($company->id, $patients, $appointments, $procedures);

        // 8. Create treatment plans
        $plans = $this->createQaTreatmentPlans($company->id, $patients, $procedures);

        // 9. Create orders, invoices, payments
        $this->createQaFinancials($company->id, $patients, $appointments, $plans, $admin->id);
    }

    /**
     * Clean old QA demo data.
     */
    private function cleanOldQaData(int $companyId): void
    {
        $qaPatientEmails = ['ahmed1@test.com', 'mona2@test.com', 'youssef3@test.com'];
        $qaDoctorEmails = ['dr.ahmed@test.com', 'dr.sara@test.com'];

        $customerIds = Customer::query()
            ->where('company_id', $companyId)
            ->whereIn('email', $qaPatientEmails)
            ->pluck('id')
            ->all();

        if (!empty($customerIds)) {
            // Delete related records in order
            TreatmentPlanItem::query()
                ->whereIn('treatment_plan_id', TreatmentPlan::query()
                    ->where('company_id', $companyId)
                    ->whereIn('customer_id', $customerIds)
                    ->pluck('id'))
                ->delete();

            TreatmentPlan::query()
                ->where('company_id', $companyId)
                ->whereIn('customer_id', $customerIds)
                ->delete();

            DentalRecord::query()
                ->where('company_id', $companyId)
                ->whereIn('customer_id', $customerIds)
                ->delete();

            Appointment::query()
                ->where('company_id', $companyId)
                ->whereIn('patient_id', $customerIds)
                ->delete();

            Order::query()
                ->where('company_id', $companyId)
                ->whereIn('customer_id', $customerIds)
                ->delete();

            $invoiceIds = Invoice::query()
                ->where('company_id', $companyId)
                ->whereIn('customer_id', $customerIds)
                ->pluck('id')
                ->all();

            if (!empty($invoiceIds)) {
                Payment::query()
                    ->where('company_id', $companyId)
                    ->whereIn('invoice_id', $invoiceIds)
                    ->delete();

                Invoice::query()
                    ->where('company_id', $companyId)
                    ->whereIn('id', $invoiceIds)
                    ->delete();
            }

            Customer::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $customerIds)
                ->delete();
        }

        Doctor::query()
            ->where('company_id', $companyId)
            ->whereIn('email', $qaDoctorEmails)
            ->delete();

        Procedure::query()
            ->where('company_id', $companyId)
            ->whereIn('name', ['Filling', 'Scaling', 'Root Canal'])
            ->delete();
    }

    /**
     * Get or create admin user.
     */
    private function getOrCreateAdmin(Company $company): User
    {
        $admin = User::query()
            ->where('company_id', $company->id)
            ->where(function ($q) {
                $q->where('role', 'admin')
                    ->orWhere('is_super_admin', true);
            })
            ->first();

        if (!$admin) {
            $admin = User::factory()->admin()->create([
                'company_id' => $company->id,
                'name' => 'QA Admin',
                'email' => 'qa-admin@test.com',
                'password' => Hash::make('123456'),
            ]);
        }

        return $admin;
    }

    /**
     * Create QA doctors.
     */
    private function createQaDoctors(int $companyId): array
    {
        return [
            Doctor::factory()->create([
                'company_id' => $companyId,
                'name' => 'Dr. Ahmed Hassan',
                'email' => 'dr.ahmed@test.com',
                'work_start' => '09:00',
                'work_end' => '17:00',
            ]),
            Doctor::factory()->create([
                'company_id' => $companyId,
                'name' => 'Dr. Sara Ali',
                'email' => 'dr.sara@test.com',
                'work_start' => '10:00',
                'work_end' => '18:00',
            ]),
        ];
    }

    /**
     * Create QA patients.
     */
    private function createQaPatients(int $companyId): array
    {
        return [
            Customer::factory()->create([
                'company_id' => $companyId,
                'name' => 'Ahmed Ali',
                'email' => 'ahmed1@test.com',
                'patient_code' => 'PT-QA-0001',
                'gender' => 'male',
            ]),
            Customer::factory()->create([
                'company_id' => $companyId,
                'name' => 'Mona Hassan',
                'email' => 'mona2@test.com',
                'patient_code' => 'PT-QA-0002',
                'gender' => 'female',
            ]),
            Customer::factory()->create([
                'company_id' => $companyId,
                'name' => 'Youssef Emad',
                'email' => 'youssef3@test.com',
                'patient_code' => 'PT-QA-0003',
                'gender' => 'male',
            ]),
        ];
    }

    /**
     * Create QA procedures.
     */
    private function createQaProcedures(int $companyId): array
    {
        return [
            Procedure::factory()->create([
                'company_id' => $companyId,
                'name' => 'Filling',
                'default_price' => 250,
            ]),
            Procedure::factory()->create([
                'company_id' => $companyId,
                'name' => 'Scaling',
                'default_price' => 400,
            ]),
            Procedure::factory()->create([
                'company_id' => $companyId,
                'name' => 'Root Canal',
                'default_price' => 1200,
            ]),
        ];
    }

    /**
     * Create QA appointments.
     */
    private function createQaAppointments(int $companyId, array $patients, array $doctors, int $adminId): array
    {
        return [
            Appointment::factory()->create([
                'company_id' => $companyId,
                'patient_id' => $patients[0]->id,
                'doctor_id' => $doctors[0]->id,
                'appointment_date' => now()->toDateString(),
                'appointment_time' => '10:00',
                'status' => 'scheduled',
                'created_by' => $adminId,
            ]),
            Appointment::factory()->completed()->create([
                'company_id' => $companyId,
                'patient_id' => $patients[1]->id,
                'doctor_id' => $doctors[0]->id,
                'appointment_date' => now()->toDateString(),
                'appointment_time' => '11:00',
                'created_by' => $adminId,
            ]),
            Appointment::factory()->cancelled()->create([
                'company_id' => $companyId,
                'patient_id' => $patients[2]->id,
                'doctor_id' => $doctors[1]->id,
                'appointment_date' => now()->toDateString(),
                'appointment_time' => '12:00',
                'created_by' => $adminId,
            ]),
            Appointment::factory()->noShow()->create([
                'company_id' => $companyId,
                'patient_id' => $patients[0]->id,
                'doctor_id' => $doctors[1]->id,
                'appointment_date' => now()->subDay()->toDateString(),
                'appointment_time' => '13:00',
                'created_by' => $adminId,
            ]),
            Appointment::factory()->future()->create([
                'company_id' => $companyId,
                'patient_id' => $patients[1]->id,
                'doctor_id' => $doctors[1]->id,
                'appointment_time' => '14:00',
                'created_by' => $adminId,
            ]),
        ];
    }

    /**
     * Create QA dental records.
     */
    private function createQaDentalRecords(int $companyId, array $patients, array $appointments, array $procedures): void
    {
        DentalRecord::factory()->create([
            'company_id' => $companyId,
            'customer_id' => $patients[0]->id,
            'appointment_id' => $appointments[0]->id,
            'procedure_id' => $procedures[0]->id,
            'tooth_number' => '16',
            'surface' => 'occlusal',
            'status' => 'planned',
        ]);

        DentalRecord::factory()->completed()->create([
            'company_id' => $companyId,
            'customer_id' => $patients[1]->id,
            'appointment_id' => $appointments[1]->id,
            'procedure_id' => $procedures[1]->id,
            'tooth_number' => '11',
            'surface' => 'full',
        ]);

        DentalRecord::factory()->inProgress()->create([
            'company_id' => $companyId,
            'customer_id' => $patients[0]->id,
            'appointment_id' => $appointments[3]->id,
            'procedure_id' => $procedures[2]->id,
            'tooth_number' => '26',
            'surface' => 'mesial',
        ]);
    }

    /**
     * Create QA treatment plans.
     */
    private function createQaTreatmentPlans(int $companyId, array $patients, array $procedures): array
    {
        $plan1 = TreatmentPlan::factory()->create([
            'company_id' => $companyId,
            'customer_id' => $patients[0]->id,
            'title' => 'Restorative Plan',
            'total_cost' => 1450,
        ]);

        TreatmentPlanItem::factory()->create([
            'company_id' => $companyId,
            'treatment_plan_id' => $plan1->id,
            'procedure_id' => $procedures[0]->id,
            'procedure' => 'Filling',
            'tooth_number' => '16',
            'price' => 250,
        ]);

        TreatmentPlanItem::factory()->create([
            'company_id' => $companyId,
            'treatment_plan_id' => $plan1->id,
            'procedure_id' => $procedures[2]->id,
            'procedure' => 'Root Canal',
            'tooth_number' => '26',
            'price' => 1200,
        ]);

        $plan2 = TreatmentPlan::factory()->create([
            'company_id' => $companyId,
            'customer_id' => $patients[1]->id,
            'title' => 'Cleaning & Follow-up',
            'total_cost' => 400,
        ]);

        TreatmentPlanItem::factory()->create([
            'company_id' => $companyId,
            'treatment_plan_id' => $plan2->id,
            'procedure_id' => $procedures[1]->id,
            'procedure' => 'Scaling',
            'tooth_number' => '11',
            'price' => 400,
        ]);

        return [$plan1, $plan2];
    }

    /**
     * Create QA financial data.
     */
    private function createQaFinancials(int $companyId, array $patients, array $appointments, array $plans, int $adminId): void
    {
        // Order 1 & Invoice 1 (unpaid)
        $order1 = Order::factory()->create([
            'company_id' => $companyId,
            'customer_id' => $patients[0]->id,
            'status' => 'confirmed',
            'total' => 1000,
            'created_by' => $adminId,
        ]);

        Invoice::factory()->create([
            'company_id' => $companyId,
            'number' => 'INV-QA-1001',
            'order_id' => $order1->id,
            'appointment_id' => $appointments[0]->id,
            'treatment_plan_id' => $plans[0]->id,
            'customer_id' => $patients[0]->id,
            'total' => 1000,
            'status' => 'unpaid',
        ]);

        // Order 2 & Invoice 2 (partially_paid)
        $order2 = Order::factory()->create([
            'company_id' => $companyId,
            'customer_id' => $patients[1]->id,
            'status' => 'confirmed',
            'total' => 500,
            'created_by' => $adminId,
        ]);

        $invoice2 = Invoice::factory()->create([
            'company_id' => $companyId,
            'number' => 'INV-QA-1002',
            'order_id' => $order2->id,
            'appointment_id' => $appointments[1]->id,
            'treatment_plan_id' => $plans[1]->id,
            'customer_id' => $patients[1]->id,
            'total' => 500,
            'status' => 'partially_paid',
        ]);

        Payment::factory()->create([
            'company_id' => $companyId,
            'invoice_id' => $invoice2->id,
            'amount' => 200,
            'applied_amount' => 200,
            'method' => 'cash',
            'received_by' => $adminId,
        ]);

        // Order 3 & Invoice 3 (paid)
        $order3 = Order::factory()->create([
            'company_id' => $companyId,
            'customer_id' => $patients[0]->id,
            'status' => 'confirmed',
            'total' => 300,
            'created_by' => $adminId,
        ]);

        $invoice3 = Invoice::factory()->create([
            'company_id' => $companyId,
            'number' => 'INV-QA-1003',
            'order_id' => $order3->id,
            'treatment_plan_id' => $plans[0]->id,
            'customer_id' => $patients[0]->id,
            'total' => 300,
            'status' => 'paid',
        ]);

        Payment::factory()->create([
            'company_id' => $companyId,
            'invoice_id' => $invoice3->id,
            'amount' => 300,
            'applied_amount' => 300,
            'method' => 'card',
            'received_by' => $adminId,
        ]);
    }

    /**
     * Display QA summary.
     */
    private function displayQaSummary(Company $company): void
    {
        $this->command->newLine();
        $this->command->info('✅ QA Demo Data Created Successfully!');
        $this->command->line("   Company: {$company->name} (ID: {$company->id})");
        $this->command->line('   Patients: 3 (Ahmed, Mona, Youssef)');
        $this->command->line('   Doctors: 2 (Dr. Ahmed, Dr. Sara)');
        $this->command->line('   Appointments: 5 (scheduled, completed, cancelled, no-show, future)');
        $this->command->line('   Invoices: 3 (unpaid, partially_paid, paid)');
    }
}
