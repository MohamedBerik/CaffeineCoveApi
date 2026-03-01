<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Appointment extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'patient_id',
        'doctor_id',
        'doctor_name',
        'appointment_date',
        'appointment_time',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'doctor_id' => 'integer',
        'patient_id' => 'integer',
    ];

    public function patient()
    {
        return $this->belongsTo(Customer::class, 'patient_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoice()
    {
        return $this->hasOne(\App\Models\Invoice::class);
    }
    public function doctor()
    {
        return $this->belongsTo(\App\Models\Doctor::class, 'doctor_id');
    }

    public function getAppointmentTimeAttribute($value)
    {
        if (!$value) return null;
        return \Carbon\Carbon::parse($value)->format('H:i');
    }
}
