<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),      // ✅ مهم
            // لو عندك أعمدة NOT NULL تانية في customers ضيفها هنا
            // مثال:
            // 'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'password' => $this->faker->password(),
        ];
    }
}
