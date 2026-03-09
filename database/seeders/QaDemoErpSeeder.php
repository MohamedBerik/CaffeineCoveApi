<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Company;
use App\Models\User;
use App\Models\Customer;
use App\Models\Doctor;
use App\Models\Procedure;
use App\Models\Appointment;
use App\Models\DentalRecord;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Payment;

class QaDemoErpSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $company = Company::query()->first();

            if (!$company) {
                throw new \RuntimeException('No company found. Create a company first.');
            }

            $admin = User::query()
                ->where('company_id', $company->id)
                ->where(function ($q) {
                    $q->where('role', 'admin')
                        ->orWhere('is_super_admin', 1);
                })
                ->first();

            if (!$admin) {
                throw new \RuntimeException('No admin user found for the selected company.');
            }

            $companyId = $company->id;
            $adminId = $admin->id;

            /*
            |--------------------------------------------------------------------------
            | Clean previous QA demo data by naming pattern
            |--------------------------------------------------------------------------
            */

            $demoPatientEmails = [
                'ahmed1@test.com',
                'mona2@test.com',
                'youssef3@test.com',
            ];

            $demoDoctorEmails = [
                'dr.ahmed@test.com',
                'dr.sara@test.com',
            ];

            $customers = Customer::query()
                ->where('company_id', $companyId)
                ->whereIn('email', $demoPatientEmails)
                ->get();

            $customerIds = $customers->pluck('id')->all();

            $doctors = Doctor::query()
                ->where('company_id', $companyId)
                ->whereIn('email', $demoDoctorEmails)
                ->get();

            $doctorIds = $doctors->pluck('id')->all();

            $invoiceIds = Invoice::query()
                ->where('company_id', $companyId)
                ->whereIn('customer_id', $customerIds)
                ->pluck('id')
                ->all();

            $paymentIds = Payment::query()
                ->where('company_id', $companyId)
                ->whereIn('invoice_id', $invoiceIds)
                ->pluck('id')
                ->all();

            DB::table('payment_refunds')
                ->where('company_id', $companyId)
                ->whereIn('payment_id', $paymentIds)
                ->delete();

            DB::table('customer_credits')
                ->where('company_id', $companyId)
                ->whereIn('customer_id', $customerIds)
                ->delete();

            Payment::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $paymentIds)
                ->delete();

            Invoice::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $invoiceIds)
                ->delete();

            TreatmentPlanItem::query()
                ->where('company_id', $companyId)
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

            Customer::query()
                ->where('company_id', $companyId)
                ->whereIn('email', $demoPatientEmails)
                ->delete();

            Doctor::query()
                ->where('company_id', $companyId)
                ->whereIn('email', $demoDoctorEmails)
                ->delete();

            Procedure::query()
                ->where('company_id', $companyId)
                ->whereIn('name', ['Filling', 'Scaling', 'Root Canal'])
                ->delete();

            /*
            |--------------------------------------------------------------------------
            | Doctors
            |--------------------------------------------------------------------------
            */

            $doctor1 = Doctor::create([
                'company_id' => $companyId,
                'name' => 'Dr. Ahmed Hassan',
                'email' => 'dr.ahmed@test.com',
                'phone' => '01010000001',
                'specialty' => 'General Dentist',
                'work_start' => '09:00',
                'work_end' => '17:00',
                'slot_minutes' => 30,
                'is_active' => 1,
            ]);

            $doctor2 = Doctor::create([
                'company_id' => $companyId,
                'name' => 'Dr. Sara Ali',
                'email' => 'dr.sara@test.com',
                'phone' => '01010000002',
                'specialty' => 'Orthodontist',
                'work_start' => '10:00',
                'work_end' => '18:00',
                'slot_minutes' => 30,
                'is_active' => 1,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Patients
            |--------------------------------------------------------------------------
            */

            $patient1 = Customer::create([
                'company_id' => $companyId,
                'name' => 'Ahmed Ali',
                'email' => 'ahmed1@test.com',
                'phone' => '0100000001',
                'gender' => 'male',
                'patient_code' => 'PT-QA-0001',
                'status' => '1',
                'notes' => 'QA demo patient 1',
            ]);

            $patient2 = Customer::create([
                'company_id' => $companyId,
                'name' => 'Mona Hassan',
                'email' => 'mona2@test.com',
                'phone' => '0100000002',
                'gender' => 'female',
                'patient_code' => 'PT-QA-0002',
                'status' => '1',
                'notes' => 'QA demo patient 2',
            ]);

            $patient3 = Customer::create([
                'company_id' => $companyId,
                'name' => 'Youssef Emad',
                'email' => 'youssef3@test.com',
                'phone' => '0100000003',
                'gender' => 'male',
                'patient_code' => 'PT-QA-0003',
                'status' => '1',
                'notes' => 'QA demo patient 3',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Procedures
            |--------------------------------------------------------------------------
            */

            $procedure1 = Procedure::create([
                'company_id' => $companyId,
                'name' => 'Filling',
                'default_price' => 250,
            ]);

            $procedure2 = Procedure::create([
                'company_id' => $companyId,
                'name' => 'Scaling',
                'default_price' => 400,
            ]);

            $procedure3 = Procedure::create([
                'company_id' => $companyId,
                'name' => 'Root Canal',
                'default_price' => 1200,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Appointments
            |--------------------------------------------------------------------------
            */

            $today = Carbon::today();
            $yesterday = Carbon::yesterday();
            $tomorrow = Carbon::tomorrow();

            $appointment1 = Appointment::create([
                'company_id' => $companyId,
                'patient_id' => $patient1->id,
                'doctor_id' => $doctor1->id,
                'doctor_name' => $doctor1->name,
                'appointment_date' => $today->toDateString(),
                'appointment_time' => '10:00',
                'status' => 'scheduled',
                'notes' => 'QA scheduled appointment',
                'created_by' => $adminId,
            ]);

            $appointment2 = Appointment::create([
                'company_id' => $companyId,
                'patient_id' => $patient2->id,
                'doctor_id' => $doctor1->id,
                'doctor_name' => $doctor1->name,
                'appointment_date' => $today->toDateString(),
                'appointment_time' => '11:00',
                'status' => 'completed',
                'notes' => 'QA completed appointment',
                'created_by' => $adminId,
            ]);

            $appointment3 = Appointment::create([
                'company_id' => $companyId,
                'patient_id' => $patient3->id,
                'doctor_id' => $doctor2->id,
                'doctor_name' => $doctor2->name,
                'appointment_date' => $today->toDateString(),
                'appointment_time' => '12:00',
                'status' => 'cancelled',
                'notes' => 'QA cancelled appointment',
                'created_by' => $adminId,
            ]);

            $appointment4 = Appointment::create([
                'company_id' => $companyId,
                'patient_id' => $patient1->id,
                'doctor_id' => $doctor2->id,
                'doctor_name' => $doctor2->name,
                'appointment_date' => $yesterday->toDateString(),
                'appointment_time' => '13:00',
                'status' => 'no_show',
                'notes' => 'QA no-show appointment',
                'created_by' => $adminId,
            ]);

            $appointment5 = Appointment::create([
                'company_id' => $companyId,
                'patient_id' => $patient2->id,
                'doctor_id' => $doctor2->id,
                'doctor_name' => $doctor2->name,
                'appointment_date' => $tomorrow->toDateString(),
                'appointment_time' => '14:00',
                'status' => 'in_progress',
                'notes' => 'QA in-progress appointment',
                'created_by' => $adminId,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Dental Records
            |--------------------------------------------------------------------------
            */

            DentalRecord::create([
                'company_id' => $companyId,
                'customer_id' => $patient1->id,
                'appointment_id' => $appointment1->id,
                'procedure_id' => $procedure1->id,
                'tooth_number' => '16',
                'surface' => 'occlusal',
                'status' => 'planned',
                'notes' => 'Needs filling',
            ]);

            DentalRecord::create([
                'company_id' => $companyId,
                'customer_id' => $patient2->id,
                'appointment_id' => $appointment2->id,
                'procedure_id' => $procedure2->id,
                'tooth_number' => '11',
                'surface' => 'full',
                'status' => 'completed',
                'notes' => 'Scaling completed',
            ]);

            DentalRecord::create([
                'company_id' => $companyId,
                'customer_id' => $patient1->id,
                'appointment_id' => $appointment4->id,
                'procedure_id' => $procedure3->id,
                'tooth_number' => '26',
                'surface' => 'mesial',
                'status' => 'in_progress',
                'notes' => 'Root canal in progress',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Treatment Plans
            |--------------------------------------------------------------------------
            */

            $plan1 = TreatmentPlan::create([
                'company_id' => $companyId,
                'customer_id' => $patient1->id,
                'title' => 'Restorative Plan',
                'notes' => 'QA restorative plan',
                'total_cost' => 1450,
                'status' => 'active',
            ]);

            TreatmentPlanItem::create([
                'company_id' => $companyId,
                'treatment_plan_id' => $plan1->id,
                'procedure_id' => $procedure1->id,
                'procedure' => 'Filling',
                'tooth_number' => '16',
                'surface' => 'occlusal',
                'notes' => 'Plan item filling',
                'price' => 250,
            ]);

            TreatmentPlanItem::create([
                'company_id' => $companyId,
                'treatment_plan_id' => $plan1->id,
                'procedure_id' => $procedure3->id,
                'procedure' => 'Root Canal',
                'tooth_number' => '26',
                'surface' => 'mesial',
                'notes' => 'Plan item root canal',
                'price' => 1200,
            ]);

            $plan2 = TreatmentPlan::create([
                'company_id' => $companyId,
                'customer_id' => $patient2->id,
                'title' => 'Cleaning & Follow-up',
                'notes' => 'QA cleaning plan',
                'total_cost' => 400,
                'status' => 'active',
            ]);

            TreatmentPlanItem::create([
                'company_id' => $companyId,
                'treatment_plan_id' => $plan2->id,
                'procedure_id' => $procedure2->id,
                'procedure' => 'Scaling',
                'tooth_number' => '11',
                'surface' => 'full',
                'notes' => 'Plan item scaling',
                'price' => 400,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Orders
            |--------------------------------------------------------------------------
            */

            $order1 = Order::create([
                'company_id' => $companyId,
                'customer_id' => $patient1->id,
                'title_en' => 'Dental Services',
                'title_ar' => 'خدمات أسنان',
                'description_en' => 'QA order 1',
                'description_ar' => 'طلب اختبار 1',
                'status' => 'confirmed',
                'total' => 1000,
                'created_by' => $adminId,
            ]);

            $order2 = Order::create([
                'company_id' => $companyId,
                'customer_id' => $patient2->id,
                'title_en' => 'Dental Services',
                'title_ar' => 'خدمات أسنان',
                'description_en' => 'QA order 2',
                'description_ar' => 'طلب اختبار 2',
                'status' => 'confirmed',
                'total' => 500,
                'created_by' => $adminId,
            ]);

            $order3 = Order::create([
                'company_id' => $companyId,
                'customer_id' => $patient1->id,
                'title_en' => 'Dental Services',
                'title_ar' => 'خدمات أسنان',
                'description_en' => 'QA order 3',
                'description_ar' => 'طلب اختبار 3',
                'status' => 'confirmed',
                'total' => 300,
                'created_by' => $adminId,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Invoices
            |--------------------------------------------------------------------------
            */

            $invoice1 = Invoice::create([
                'company_id' => $companyId,
                'number' => 'INV-QA-1001',
                'order_id' => $order1->id,
                'appointment_id' => $appointment1->id,
                'treatment_plan_id' => $plan1->id,
                'customer_id' => $patient1->id,
                'total' => 1000,
                'status' => 'unpaid',
                'issued_at' => $today->copy()->setTime(10, 30, 0),
            ]);

            $invoice2 = Invoice::create([
                'company_id' => $companyId,
                'number' => 'INV-QA-1002',
                'order_id' => $order2->id,
                'appointment_id' => $appointment2->id,
                'treatment_plan_id' => $plan2->id,
                'customer_id' => $patient2->id,
                'total' => 500,
                'status' => 'partially_paid',
                'issued_at' => $today->copy()->setTime(11, 30, 0),
            ]);

            $invoice3 = Invoice::create([
                'company_id' => $companyId,
                'number' => 'INV-QA-1003',
                'order_id' => $order3->id,
                'appointment_id' => null,
                'treatment_plan_id' => $plan1->id,
                'customer_id' => $patient1->id,
                'total' => 300,
                'status' => 'paid',
                'issued_at' => $today->copy()->setTime(12, 30, 0),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Payments
            |--------------------------------------------------------------------------
            */

            $payment1 = Payment::create([
                'company_id' => $companyId,
                'invoice_id' => $invoice2->id,
                'amount' => 200,
                'applied_amount' => 200,
                'credit_amount' => 0,
                'method' => 'cash',
                'paid_at' => $today->copy()->setTime(13, 0, 0),
                'received_by' => $adminId,
            ]);

            $payment2 = Payment::create([
                'company_id' => $companyId,
                'invoice_id' => $invoice3->id,
                'amount' => 300,
                'applied_amount' => 300,
                'credit_amount' => 0,
                'method' => 'card',
                'paid_at' => $today->copy()->setTime(14, 0, 0),
                'received_by' => $adminId,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Refunds
            |--------------------------------------------------------------------------
            */

            DB::table('payment_refunds')->insert([
                'company_id' => $companyId,
                'payment_id' => $payment2->id,
                'applies_to' => 'invoice',
                'amount' => 50,
                'refunded_at' => $today->copy()->setTime(15, 0, 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Customer Credits
            |--------------------------------------------------------------------------
            */

            DB::table('customer_credits')->insert([
                'company_id' => $companyId,
                'customer_id' => $patient1->id,
                'invoice_id' => null,
                'payment_id' => $payment2->id,
                'refund_id' => null,
                'type' => 'credit',
                'amount' => 75,
                'entry_date' => $today->toDateString(),
                'description' => 'QA demo credit balance',
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
