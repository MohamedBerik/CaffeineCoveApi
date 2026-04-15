<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\TreatmentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class TreatmentPlanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TreatmentPlan::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'title' => $this->generatePlanTitle(),
            'notes' => $this->faker->optional(0.6)->paragraph(),
            'total_cost' => $this->faker->numberBetween(500, 15000),
            'status' => $this->faker->randomElement(['active', 'completed', 'cancelled']),
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => fn(array $attributes) => $attributes['created_at'],
        ];
    }

    /**
     * Generate a realistic treatment plan title.
     */
    private function generatePlanTitle(): string
    {
        $titles = [
            'خطة علاج - ' . $this->faker->randomElement(['تنظيف', 'تبييض', 'تقويم', 'حشو', 'علاج عصب']),
            'Treatment Plan - ' . $this->faker->randomElement(['Cleaning', 'Whitening', 'Orthodontic', 'Restorative']),
            $this->faker->randomElement(['Full Mouth Rehabilitation', 'Smile Makeover', 'Preventive Care Plan']),
            'خطة علاجية متكاملة - ' . $this->faker->monthName(),
        ];

        return $this->faker->randomElement($titles);
    }

    /**
     * Indicate that the plan is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the plan is completed.
     */
    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the plan is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the plan has a high total cost.
     */
    public function highCost(): static
    {
        return $this->state(fn(array $attributes) => [
            'total_cost' => $this->faker->numberBetween(5000, 20000),
        ]);
    }

    /**
     * Indicate that the plan has a low total cost.
     */
    public function lowCost(): static
    {
        return $this->state(fn(array $attributes) => [
            'total_cost' => $this->faker->numberBetween(200, 1000),
        ]);
    }

    /**
     * Indicate that the plan has detailed notes.
     */
    public function withDetailedNotes(): static
    {
        return $this->state(fn(array $attributes) => [
            'notes' => $this->faker->paragraphs(2, true),
        ]);
    }

    /**
     * Indicate that the plan is for a specific company and customer.
     */
    public function forCompanyAndCustomer(int $companyId, int $customerId): static
    {
        return $this->state(fn(array $attributes) => [
            'company_id' => $companyId,
            'customer_id' => $customerId,
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (TreatmentPlan $plan) {
            // ضبط total_cost لو مش موجود
            if (!$plan->total_cost || $plan->total_cost < 0) {
                $plan->total_cost = 1000;
            }

            // ضبط عنوان افتراضي
            if (!$plan->title) {
                $plan->title = 'Treatment Plan';
            }
        })->afterCreating(function (TreatmentPlan $plan) {
            // التأكد من إن customer تابع لنفس company
            if ($plan->customer && $plan->customer->company_id !== $plan->company_id) {
                $plan->customer->update(['company_id' => $plan->company_id]);
            }
        });
    }
}
