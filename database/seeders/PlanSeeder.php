<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    public function run()
    {
        $plans = [
            [
                'name' => 'Basic',
                'name_ar' => 'أساسي',
                'description' => 'Perfect for small clinics',
                'description_ar' => 'مناسب للعيادات الصغيرة',
                'price_monthly' => 99,
                'price_yearly' => 990,
                'max_users' => 3,
                'max_patients' => 500,
                'max_appointments' => 100,
                'features' => ['Up to 3 users', '500 patients', 'Basic reports', 'Email support'],
                'is_active' => true,
            ],
            [
                'name' => 'Professional',
                'name_ar' => 'احترافي',
                'description' => 'For growing clinics',
                'description_ar' => 'للعايدات النامية',
                'price_monthly' => 199,
                'price_yearly' => 1990,
                'max_users' => 10,
                'max_patients' => 2000,
                'max_appointments' => 500,
                'features' => ['Up to 10 users', '2000 patients', 'Advanced reports', 'Priority support', 'SMS notifications'],
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'name_ar' => 'مؤسسي',
                'description' => 'For large dental chains',
                'description_ar' => 'لسلاسل العيادات الكبيرة',
                'price_monthly' => 399,
                'price_yearly' => 3990,
                'max_users' => 50,
                'max_patients' => null,
                'max_appointments' => null,
                'features' => ['Unlimited users', 'Unlimited patients', 'Custom reports', '24/7 support', 'API access', 'Dedicated account manager'],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
