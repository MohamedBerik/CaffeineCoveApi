<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'company_id' => 1,
            'name' => $this->faker->name,
            'is_active' => true,
            'work_start' => '09:00',
            'work_end' => '17:00',
            'slot_minutes' => 30,
        ];
    }
}
