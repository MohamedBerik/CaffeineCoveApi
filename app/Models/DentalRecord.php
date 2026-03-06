<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class DentalRecord extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'customer_id',
        'appointment_id',
        'procedure_id',
        'tooth_number',
        'surface',
        'status',
        'notes',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }
}
