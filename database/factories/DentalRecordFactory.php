<?php

namespace Database\Factories;

use App\Models\DentalRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalRecordFactory extends Factory
{
    protected $model = DentalRecord::class;

    public function definition()
    {
        return [
            'company_id' => 1,
            'customer_id' => 1,
            'appointment_id' => null,
            'procedure_id' => null,
            'tooth_number' => '16',
            'surface' => 'occlusal',
            'status' => 'planned',
            'notes' => null,
        ];
    }
}
