<?php

namespace Database\Factories;

use App\Models\TreatmentPlan;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class TreatmentPlanFactory extends Factory
{
    protected $model = TreatmentPlan::class;

    public function definition()
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create([
            'company_id' => $company->id,
        ]);

        return [
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'title'       => 'Treatment Plan',
            'notes'       => null,
            'total_cost'  => 1000,
            'status'      => 'active',
        ];
    }
}
