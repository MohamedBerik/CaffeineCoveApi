<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AppointmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Appointment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-1 month', '+1 month');
        $time = $this->faker->randomElement(['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30']);

        return [
            'company_id' => Company::factory(),
            'patient_id' => Customer::factory(),
            'doctor_id' => Doctor::factory(),
            'doctor_name' => fn(array $attributes) => Doctor::find($attributes['doctor_id'])?->name ?? 'Dr. ' . $this->faker->name(),
            'appointment_date' => Carbon::parse($date)->toDateString(),
            'appointment_time' => $time,
            'appointment_type' => $this->faker->randomElement(['consultation', 'treatment', 'follow_up', 'emergency']),
            'status' => $this->faker->randomElement(['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show']),
            'notes' => $this->faker->optional(0.6)->sentence(),
            'created_by' => User::factory(),
            'clinical_notes' => $this->faker->optional(0.3)->paragraph(),
            'diagnosis' => $this->faker->optional(0.3)->sentence(),
            'next_step' => $this->faker->optional(0.3)->sentence(),
            'reminder_status' => 'pending',
            'reminder_stage' => 1,
            'reminder_sent_count' => 0,
            'reminder_retry_count' => 0,
            'follow_up_status' => null,
            'follow_up_state' => null,
            'follow_up_retry_count' => 0,
        ];
    }

    /**
     * Indicate that the appointment is scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'scheduled',
        ]);
    }

    /**
     * Indicate that the appointment is completed.
     */
    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the appointment is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the appointment is no-show.
     */
    public function noShow(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'no_show',
        ]);
    }

    /**
     * Indicate that the appointment is in the past.
     */
    public function past(): static
    {
        return $this->state(fn(array $attributes) => [
            'appointment_date' => $this->faker->dateTimeBetween('-1 month', '-1 day')->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the appointment is in the future.
     */
    public function future(): static
    {
        return $this->state(fn(array $attributes) => [
            'appointment_date' => $this->faker->dateTimeBetween('+1 day', '+1 month')->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the appointment is today.
     */
    public function today(): static
    {
        return $this->state(fn(array $attributes) => [
            'appointment_date' => Carbon::today()->toDateString(),
        ]);
    }

    /**
     * Indicate that the appointment is for consultation.
     */
    public function consultation(): static
    {
        return $this->state(fn(array $attributes) => [
            'appointment_type' => 'consultation',
        ]);
    }

    /**
     * Indicate that the appointment is for treatment.
     */
    public function treatment(): static
    {
        return $this->state(fn(array $attributes) => [
            'appointment_type' => 'treatment',
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Appointment $appointment) {
            // Set reminder based on appointment date
            if ($appointment->status === 'scheduled') {
                $appointmentDateTime = Carbon::parse($appointment->appointment_date . ' ' . $appointment->appointment_time);

                if ($appointmentDateTime->isFuture()) {
                    $appointment->next_reminder_at = $appointmentDateTime->copy()->subDay();
                }
            }

            // Set follow-up for completed appointments
            if ($appointment->status === 'completed' && $this->faker->boolean(70)) {
                $appointment->follow_up_state = 'pending';
                $appointment->follow_up_at = Carbon::parse($appointment->appointment_date . ' ' . $appointment->appointment_time)
                    ->addDay();
            }
        });
    }
}
