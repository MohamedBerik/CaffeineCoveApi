<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $gender = $this->faker->randomElement(['male', 'female']);

        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->name($gender),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'patient_code' => 'PT-' . str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'date_of_birth' => $this->faker->dateTimeBetween('-80 years', '-5 years')->format('Y-m-d'),
            'gender' => $gender,
            'address' => $this->faker->optional(0.7)->address(),
            'notes' => $this->faker->optional(0.4)->sentence(),
            'status' => $this->faker->randomElement(['0', '1']),
            'created_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'updated_at' => fn(array $attributes) => $attributes['created_at'],
        ];
    }

    /**
     * Indicate that the customer is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => '1',
        ]);
    }

    /**
     * Indicate that the customer is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => '0',
        ]);
    }

    /**
     * Indicate that the customer is male.
     */
    public function male(): static
    {
        return $this->state(fn(array $attributes) => [
            'gender' => 'male',
            'name' => $this->faker->name('male'),
        ]);
    }

    /**
     * Indicate that the customer is female.
     */
    public function female(): static
    {
        return $this->state(fn(array $attributes) => [
            'gender' => 'female',
            'name' => $this->faker->name('female'),
        ]);
    }

    /**
     * Indicate that the customer has full contact information.
     */
    public function withFullContact(): static
    {
        return $this->state(fn(array $attributes) => [
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'address' => $this->faker->address(),
        ]);
    }

    /**
     * Indicate that the customer has medical notes.
     */
    public function withMedicalNotes(): static
    {
        $notes = [
            'حساسية من البنسلين',
            'ضغط دم مرتفع',
            'سكري',
            'حامل',
            'مدخن',
            'يستخدم مميعات دم',
            'حساسية من اللاتكس',
            'ربو',
        ];

        return $this->state(fn(array $attributes) => [
            'notes' => $this->faker->randomElement($notes) . '. ' . $this->faker->sentence(),
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Customer $customer) {
            // ضمان uniqueness للـ patient_code
            if (!$customer->patient_code) {
                $customer->patient_code = 'PT-' . str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT);
            }
        });
    }
}
