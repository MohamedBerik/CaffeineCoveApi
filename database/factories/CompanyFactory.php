<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'status' => $this->faker->randomElement(['active', 'trial', 'suspended', 'cancelled']),
            'trial_ends_at' => $this->faker->optional(0.7)->dateTimeBetween('+7 days', '+30 days'),
            'branding' => [
                'app_name' => $name,
                'logo' => null,
                'primary_color' => $this->faker->hexColor(),
                'secondary_color' => $this->faker->hexColor(),
            ],
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => fn(array $attributes) => $attributes['created_at'],
        ];
    }

    /**
     * Indicate that the company is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Company::STATUS_ACTIVE,
            'trial_ends_at' => null,
        ]);
    }

    /**
     * Indicate that the company is on trial.
     */
    public function trial(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Company::STATUS_TRIAL,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    /**
     * Indicate that the company is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Company::STATUS_SUSPENDED,
        ]);
    }

    /**
     * Indicate that the company is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Company::STATUS_CANCELLED,
        ]);
    }

    /**
     * Indicate that the trial has expired.
     */
    public function trialExpired(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Company::STATUS_TRIAL,
            'trial_ends_at' => now()->subDays($this->faker->numberBetween(1, 30)),
        ]);
    }

    /**
     * Indicate that the company has custom branding.
     */
    public function withBranding(): static
    {
        return $this->state(fn(array $attributes) => [
            'branding' => [
                'app_name' => $attributes['name'] . ' Clinic',
                'logo' => 'logos/' . Str::random(10) . '.png',
                'primary_color' => '#1a237e',
                'secondary_color' => '#64b5f6',
            ],
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Company $company) {
            // لو الشركة trial، نتأكد إن trial_ends_at موجود
            if ($company->status === Company::STATUS_TRIAL && !$company->trial_ends_at) {
                $company->update(['trial_ends_at' => now()->addDays(14)]);
            }

            // لو الشركة active أو suspended، نخلي trial_ends_at = null
            if (in_array($company->status, [Company::STATUS_ACTIVE, Company::STATUS_SUSPENDED, Company::STATUS_CANCELLED])) {
                $company->update(['trial_ends_at' => null]);
            }
        });
    }
}
