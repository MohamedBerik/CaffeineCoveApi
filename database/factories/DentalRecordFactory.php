<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\DentalRecord;
use App\Models\Procedure;
use App\Models\Appointment;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalRecordFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DentalRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $toothNumber = $this->faker->randomElement([
            '11',
            '12',
            '13',
            '14',
            '15',
            '16',
            '17',
            '18',
            '21',
            '22',
            '23',
            '24',
            '25',
            '26',
            '27',
            '28',
            '31',
            '32',
            '33',
            '34',
            '35',
            '36',
            '37',
            '38',
            '41',
            '42',
            '43',
            '44',
            '45',
            '46',
            '47',
            '48',
        ]);

        $surface = $this->faker->optional(0.6)->randomElement([
            'occlusal',
            'mesial',
            'distal',
            'buccal',
            'lingual',
            'palatal',
            'facial',
            'incisal'
        ]);

        $status = $this->faker->randomElement(['planned', 'in_progress', 'completed', 'cancelled']);

        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'appointment_id' => null,
            'doctor_id' => Doctor::factory(),
            'procedure_id' => Procedure::factory(),
            'tooth_number' => $toothNumber,
            'surface' => $surface,
            'status' => $status,
            'notes' => $this->faker->optional(0.4)->sentence(),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => fn(array $attributes) => $attributes['created_at'],
        ];
    }

    /**
     * Indicate that the record is planned.
     */
    public function planned(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'planned',
        ]);
    }

    /**
     * Indicate that the record is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'in_progress',
        ]);
    }

    /**
     * Indicate that the record is completed.
     */
    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the record is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the record is linked to an appointment.
     */
    public function withAppointment(): static
    {
        return $this->state(fn(array $attributes) => [
            'appointment_id' => Appointment::factory(),
        ]);
    }

    /**
     * Indicate that the record is for a specific tooth.
     */
    public function forTooth(string $toothNumber): static
    {
        return $this->state(fn(array $attributes) => [
            'tooth_number' => $toothNumber,
        ]);
    }

    /**
     * Indicate that the record has detailed notes.
     */
    public function withDetailedNotes(): static
    {
        $procedures = ['حشو', 'تنظيف', 'خلع', 'تبييض', 'تركيب تاج', 'علاج عصب'];
        $materials = ['كومبوزيت', 'أملغم', 'سيراميك', 'زيركون'];

        $note = $this->faker->randomElement($procedures) . ' - ' .
            $this->faker->randomElement($materials) . '. ' .
            $this->faker->sentence();

        return $this->state(fn(array $attributes) => [
            'notes' => $note,
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (DentalRecord $record) {
            // لو فيه appointment_id، نخلي الدكتور هو نفس دكتور الموعد
            if ($record->appointment_id && !$record->doctor_id) {
                $appointment = Appointment::find($record->appointment_id);
                if ($appointment) {
                    $record->doctor_id = $appointment->doctor_id;
                }
            }
        });
    }
}
