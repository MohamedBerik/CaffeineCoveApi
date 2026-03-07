<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

use App\Models\Account;
use App\Models\Appointment;
use App\Models\ClinicSetting;
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

class DemoErpSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // -------------------------------------------------
            // Company
            // -------------------------------------------------
            $company = Company::firstOrCreate(
                ['name' => 'Demo Dental Clinic'],
                ['name' => 'Demo Dental Clinic']
            );

            // -------------------------------------------------
            // Clinic settings
            // -------------------------------------------------
            ClinicSetting::updateOrCreate(
                ['company_id' => $company->id],
                [
                    'clinic_name' => 'Demo Dental Clinic',
                    'phone' => '01000000000',
                    'email' => 'clinic@demo.com',
                    'currency' => 'USD',
                    'timezone' => 'UTC',
                    'invoice_prefix' => 'INV',
                    'invoice_start_number' => 1,
                    'next_invoice_number' => 3,
                    'language' => 'en',
                ]
            );

            // -------------------------------------------------
            // Admin user
            // -------------------------------------------------
            $admin = User::updateOrCreate(
                ['email' => 'admin@demo.com'],
                [
                    'name' => 'Admin Demo',
                    'password' => Hash::make('12345678'),
                    'company_id' => $company->id,
                    'role' => 'admin',
                    'status' => 1,
                    'is_super_admin' => 0,
                ]
            );

            // -------------------------------------------------
            // Accounts
            // -------------------------------------------------
            $this->createAccount($company->id, '1000', 'Cash');
            $this->createAccount($company->id, '1100', 'Accounts Receivable');
            $this->createAccount($company->id, '2100', 'Customer Credit');

            // -------------------------------------------------
            // Doctors
            // -------------------------------------------------
            $doctor1 = Doctor::updateOrCreate(
                ['company_id' => $company->id, 'name' => 'Dr. John Smith'],
                [
                    'is_active' => 1,
                    'work_start' => '09:00',
                    'work_end' => '17:00',
                    'slot_minutes' => 30,
                ]
            );

            $doctor2 = Doctor::updateOrCreate(
                ['company_id' => $company->id, 'name' => 'Dr. Sarah Lee'],
                [
                    'is_active' => 1,
                    'work_start' => '10:00',
                    'work_end' => '18:00',
                    'slot_minutes' => 30,
                ]
            );

            // -------------------------------------------------
            // Customers / Patients
            // -------------------------------------------------
            $patient1 = Customer::updateOrCreate(
                ['company_id' => $company->id, 'email' => 'ahmed@demo.com'],
                [
                    'name' => 'Ahmed Ali',
                    'status' => '1',
                    'phone' => '01011111111',
                    'patient_code' => 'P-0001',
                    'gender' => 'male',
                    'address' => 'Cairo',
                    'notes' => 'Regular patient',
                ]
            );

            $patient2 = Customer::updateOrCreate(
                ['company_id' => $company->id, 'email' => 'sara@demo.com'],
                [
                    'name' => 'Sara Hassan',
                    'status' => '1',
                    'phone' => '01022222222',
                    'patient_code' => 'P-0002',
                    'gender' => 'female',
                    'address' => 'Giza',
                    'notes' => 'Follow-up visits',
                ]
            );

            // -------------------------------------------------
            // Procedures
            // -------------------------------------------------
            $filling = Procedure::updateOrCreate(
                ['company_id' => $company->id, 'name' => 'Filling'],
                [
                    'default_price' => 250,
                    'is_active' => 1,
                ]
            );

            $rootCanal = Procedure::updateOrCreate(
                ['company_id' => $company->id, 'name' => 'Root Canal'],
                [
                    'default_price' => 450,
                    'is_active' => 1,
                ]
            );

            $cleaning = Procedure::updateOrCreate(
                ['company_id' => $company->id, 'name' => 'Cleaning'],
                [
                    'default_price' => 150,
                    'is_active' => 1,
                ]
            );

            // -------------------------------------------------
            // Appointments
            // -------------------------------------------------
            $today = Carbon::today();

            $appointment1 = Appointment::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'patient_id' => $patient1->id,
                    'doctor_id' => $doctor1->id,
                    'appointment_date' => $today->toDateString(),
                    'appointment_time' => '10:00',
                ],
                [
                    'doctor_name' => $doctor1->name,
                    'status' => 'scheduled',
                    'notes' => 'Checkup and filling',
                    'created_by' => $admin->id,
                ]
            );

            $appointment2 = Appointment::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'patient_id' => $patient2->id,
                    'doctor_id' => $doctor2->id,
                    'appointment_date' => $today->toDateString(),
                    'appointment_time' => '11:00',
                ],
                [
                    'doctor_name' => $doctor2->name,
                    'status' => 'completed',
                    'notes' => 'Completed cleaning',
                    'created_by' => $admin->id,
                ]
            );

            // -------------------------------------------------
            // Treatment plan
            // -------------------------------------------------
            $plan = TreatmentPlan::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'customer_id' => $patient1->id,
                    'title' => 'Ahmed Dental Plan',
                ],
                [
                    'notes' => 'Initial treatment plan',
                    'total_cost' => 700,
                    'status' => 'active',
                ]
            );

            TreatmentPlanItem::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'treatment_plan_id' => $plan->id,
                    'procedure' => 'Filling',
                    'tooth_number' => '16',
                    'surface' => 'occlusal',
                ],
                [
                    'procedure_id' => $filling->id,
                    'notes' => 'Filling needed',
                    'price' => 250,
                ]
            );

            TreatmentPlanItem::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'treatment_plan_id' => $plan->id,
                    'procedure' => 'Root Canal',
                    'tooth_number' => '11',
                    'surface' => null,
                ],
                [
                    'procedure_id' => $rootCanal->id,
                    'notes' => 'Root canal planned',
                    'price' => 450,
                ]
            );

            // -------------------------------------------------
            // Dental records
            // -------------------------------------------------
            DentalRecord::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'customer_id' => $patient1->id,
                    'appointment_id' => $appointment1->id,
                    'procedure_id' => $filling->id,
                    'tooth_number' => '16',
                ],
                [
                    'surface' => 'occlusal',
                    'status' => 'planned',
                    'notes' => 'Initial chart record',
                ]
            );

            DentalRecord::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'customer_id' => $patient2->id,
                    'appointment_id' => $appointment2->id,
                    'procedure_id' => $cleaning->id,
                    'tooth_number' => '26',
                ],
                [
                    'surface' => null,
                    'status' => 'completed',
                    'notes' => 'Cleaning done',
                ]
            );

            // -------------------------------------------------
            // Orders / Invoices / Payments
            // -------------------------------------------------
            $order1 = Order::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'customer_id' => $patient2->id,
                    'title_en' => 'Cleaning Services',
                ],
                [
                    'title_ar' => 'خدمات تنظيف',
                    'description_en' => 'Completed cleaning visit',
                    'description_ar' => 'زيارة تنظيف مكتملة',
                    'status' => 'confirmed',
                    'total' => 150,
                    'created_by' => $admin->id,
                ]
            );

            $invoice1 = Invoice::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'number' => 'INV-0001',
                ],
                [
                    'order_id' => $order1->id,
                    'appointment_id' => $appointment2->id,
                    'treatment_plan_id' => null,
                    'customer_id' => $patient2->id,
                    'total' => 150,
                    'status' => 'paid',
                    'issued_at' => now(),
                ]
            );

            $payment1 = Payment::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'invoice_id' => $invoice1->id,
                    'amount' => 150,
                    'method' => 'cash',
                ],
                [
                    'paid_at' => now(),
                    'received_by' => $admin->id,
                ]
            );

            $payment1->forceFill([
                'applied_amount' => 150,
                'credit_amount' => 0,
                'paid_at' => now(),
            ])->save();

            $order2 = Order::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'customer_id' => $patient1->id,
                    'title_en' => 'Treatment Plan Services',
                ],
                [
                    'title_ar' => 'خدمات خطة علاج',
                    'description_en' => 'Treatment plan partial billing',
                    'description_ar' => 'فاتورة جزئية لخطة علاج',
                    'status' => 'confirmed',
                    'total' => 700,
                    'created_by' => $admin->id,
                ]
            );

            Invoice::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'number' => 'INV-0002',
                ],
                [
                    'order_id' => $order2->id,
                    'appointment_id' => $appointment1->id,
                    'treatment_plan_id' => $plan->id,
                    'customer_id' => $patient1->id,
                    'total' => 700,
                    'status' => 'unpaid',
                    'issued_at' => now(),
                ]
            );
        });
    }

    private function createAccount(int $companyId, string $code, string $name): void
    {
        Account::firstOrCreate(
            ['company_id' => $companyId, 'code' => $code],
            ['name' => $name]
        );
    }
}
