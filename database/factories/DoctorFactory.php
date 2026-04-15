<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Doctor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DoctorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Doctor::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $gender = $this->faker->randomElement(['male', 'female']);
        $title = $gender === 'male' ? 'Dr.' : 'Dr.';

        return [
            'company_id' => Company::factory(),
            'name' => $title . ' ' . $this->faker->name($gender),
            'phone' => $this->faker->optional(0.8)->phoneNumber(),
            'email' => $this->faker->optional(0.7)->safeEmail(),
            'is_active' => $this->faker->boolean(85), // 85% active
            'work_start' => $this->faker->randomElement(['08:00', '09:00', '10:00']),
            'work_end' => $this->faker->randomElement(['16:00', '17:00', '18:00', '20:00', '21:00']),
            'slot_minutes' => $this->faker->randomElement([15, 30, 45, 60]),
            'created_by' => User::factory(),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => fn(array $attributes) => $attributes['created_at'],
        ];
    }

    /**
     * Indicate that the doctor is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the doctor is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the doctor works morning shift.
     */
    public function morningShift(): static
    {
        return $this->state(fn(array $attributes) => [
            'work_start' => '09:00',
            'work_end' => '17:00',
        ]);
    }

    /**
     * Indicate that the doctor works evening shift.
     */
    public function eveningShift(): static
    {
        return $this->state(fn(array $attributes) => [
            'work_start' => '14:00',
            'work_end' => '21:00',
        ]);
    }

    /**
     * Indicate that the doctor works long shift.
     */
    public function longShift(): static
    {
        return $this->state(fn(array $attributes) => [
            'work_start' => '09:00',
            'work_end' => '21:00',
        ]);
    }

    /**
     * Indicate that the doctor has short appointment slots.
     */
    public function shortSlots(): static
    {
        return $this->state(fn(array $attributes) => [
            'slot_minutes' => 15,
        ]);
    }

    /**
     * Indicate that the doctor has standard appointment slots.
     */
    public function standardSlots(): static
    {
        return $this->state(fn(array $attributes) => [
            'slot_minutes' => 30,
        ]);
    }

    /**
     * Indicate that the doctor has long appointment slots.
     */
    public function longSlots(): static
    {
        return $this->state(fn(array $attributes) => [
            'slot_minutes' => 60,
        ]);
    }

    /**
     * Indicate that the doctor is male.
     */
    public function male(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Dr. ' . $this->faker->name('male'),
        ]);
    }

    /**
     * Indicate that the doctor is female.
     */
    public function female(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Dr. ' . $this->faker->name('female'),
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Doctor $doctor) {
            // ضبط work_end لو أقل من work_start
            if ($doctor->work_end <= $doctor->work_start) {
                $doctor->work_end = Carbon::parse($doctor->work_start)->addHours(8)->format('H:i');
            }

            // إضافة بريد إلكتروني لو مش موجود
            if (!$doctor->email) {
                $slug = Str::slug($doctor->name);
                $doctor->email = $slug . '.' . $this->faker->unique()->numberBetween(1, 999) . '@example.com';
            }
        });
    }
}
