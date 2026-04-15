<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'), // password
            'role' => $this->faker->randomElement(['admin', 'doctor', 'receptionist', 'user']),
            'status' => $this->faker->randomElement([0, 1]),
            'is_super_admin' => false,
            'remember_token' => Str::random(10),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => fn(array $attributes) => $attributes['created_at'],
        ];
    }

    /**
     * Indicate that the user is a super admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn(array $attributes) => [
            'company_id' => null,
            'role' => 'admin',
            'is_super_admin' => true,
            'status' => 1,
        ]);
    }

    /**
     * Indicate that the user is a company admin.
     */
    public function admin(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'admin',
            'is_super_admin' => false,
            'status' => 1,
        ]);
    }

    /**
     * Indicate that the user is a doctor.
     */
    public function doctor(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'doctor',
            'is_super_admin' => false,
            'status' => 1,
        ]);
    }

    /**
     * Indicate that the user is a receptionist.
     */
    public function receptionist(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'receptionist',
            'is_super_admin' => false,
            'status' => 1,
        ]);
    }

    /**
     * Indicate that the user is a regular user.
     */
    public function regularUser(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'user',
            'is_super_admin' => false,
            'status' => 1,
        ]);
    }

    /**
     * Indicate that the user is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 1,
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 0,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user belongs to a specific company.
     */
    public function forCompany(int $companyId): static
    {
        return $this->state(fn(array $attributes) => [
            'company_id' => $companyId,
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            // Super admin مينفعش يكون ليه company_id
            if ($user->is_super_admin) {
                $user->company_id = null;
            }

            // Regular user لازم يكون ليه company_id
            if (!$user->is_super_admin && !$user->company_id) {
                $user->company_id = Company::factory()->create()->id;
            }
        });
    }
}
