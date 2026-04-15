<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Procedure;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcedureFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Procedure::class;

    /**
     * Common dental procedures with realistic prices.
     *
     * @var array
     */
    protected array $dentalProcedures = [
        'Teeth Cleaning' => ['ar' => 'تنظيف أسنان', 'price' => [200, 400]],
        'Teeth Whitening' => ['ar' => 'تبييض أسنان', 'price' => [800, 2000]],
        'Dental Filling' => ['ar' => 'حشو أسنان', 'price' => [300, 800]],
        'Root Canal Treatment' => ['ar' => 'علاج عصب', 'price' => [1000, 2500]],
        'Tooth Extraction' => ['ar' => 'خلع سن', 'price' => [200, 600]],
        'Dental Crown' => ['ar' => 'تاج أسنان', 'price' => [1500, 3500]],
        'Dental Bridge' => ['ar' => 'جسر أسنان', 'price' => [2000, 5000]],
        'Dental Implant' => ['ar' => 'زراعة أسنان', 'price' => [5000, 12000]],
        'Orthodontic Consultation' => ['ar' => 'استشارة تقويم', 'price' => [150, 300]],
        'Braces Installation' => ['ar' => 'تركيب تقويم', 'price' => [5000, 15000]],
        'Dentures' => ['ar' => 'طقم أسنان', 'price' => [2000, 6000]],
        'Gum Treatment' => ['ar' => 'علاج لثة', 'price' => [300, 800]],
        'X-Ray (Panoramic)' => ['ar' => 'أشعة بانوراما', 'price' => [150, 300]],
        'X-Ray (Cephalometric)' => ['ar' => 'أشعة سيفالومترية', 'price' => [100, 250]],
        'Fluoride Treatment' => ['ar' => 'علاج بالفلورايد', 'price' => [50, 150]],
        'Sealant Application' => ['ar' => 'وضع مادة سادة', 'price' => [100, 250]],
    ];

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        // اختيار إجراء عشوائي من القائمة أو إنشاء واحد جديد
        if ($this->faker->boolean(70)) {
            $procedure = $this->faker->randomElement(array_keys($this->dentalProcedures));
            $priceRange = $this->dentalProcedures[$procedure]['price'];
            $price = $this->faker->numberBetween($priceRange[0], $priceRange[1]);
        } else {
            $procedure = $this->faker->unique()->words(2, true);
            $price = $this->faker->numberBetween(50, 1000);
        }

        return [
            'company_id' => Company::factory(),
            'name' => ucwords($procedure),
            'default_price' => $price,
            'is_active' => $this->faker->boolean(80), // 80% active
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => fn(array $attributes) => $attributes['created_at'],
        ];
    }

    /**
     * Indicate that the procedure is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the procedure is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the procedure is low cost.
     */
    public function lowCost(): static
    {
        return $this->state(fn(array $attributes) => [
            'default_price' => $this->faker->numberBetween(50, 200),
        ]);
    }

    /**
     * Indicate that the procedure is medium cost.
     */
    public function mediumCost(): static
    {
        return $this->state(fn(array $attributes) => [
            'default_price' => $this->faker->numberBetween(200, 800),
        ]);
    }

    /**
     * Indicate that the procedure is high cost.
     */
    public function highCost(): static
    {
        return $this->state(fn(array $attributes) => [
            'default_price' => $this->faker->numberBetween(800, 5000),
        ]);
    }

    /**
     * Use a specific procedure from the predefined list.
     */
    public function cleaning(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Teeth Cleaning',
            'default_price' => $this->faker->numberBetween(200, 400),
        ]);
    }

    /**
     * Use a specific procedure from the predefined list.
     */
    public function filling(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Dental Filling',
            'default_price' => $this->faker->numberBetween(300, 800),
        ]);
    }

    /**
     * Use a specific procedure from the predefined list.
     */
    public function rootCanal(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Root Canal Treatment',
            'default_price' => $this->faker->numberBetween(1000, 2500),
        ]);
    }

    /**
     * Use a specific procedure from the predefined list.
     */
    public function implant(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Dental Implant',
            'default_price' => $this->faker->numberBetween(5000, 12000),
        ]);
    }

    /**
     * Use a specific procedure from the predefined list.
     */
    public function whitening(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Teeth Whitening',
            'default_price' => $this->faker->numberBetween(800, 2000),
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Procedure $procedure) {
            // ضبط سعر افتراضي لو مش موجود
            if (!$procedure->default_price || $procedure->default_price < 0) {
                $procedure->default_price = 100;
            }
        });
    }
}
