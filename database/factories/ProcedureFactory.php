<?php

namespace Database\Factories;

use App\Models\Procedure;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcedureFactory extends Factory
{
    protected $model = Procedure::class;

    public function definition()
    {
        return [
            'company_id' => 1,
            'name' => $this->faker->unique()->words(2, true),
            'default_price' => $this->faker->numberBetween(50, 500),
            'is_active' => true,
        ];
    }
}
