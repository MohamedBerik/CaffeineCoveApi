<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Appointment extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'patient_id',
        'doctor_id',
        'doctor_name',
        'appointment_date',
        'appointment_time',
        'appointment_type',
        'status',
        'notes',
        'created_by',
        'clinical_notes',
        'diagnosis',
        'next_step',
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
        return $this->hasOne(Invoice::class, 'appointment_id');
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

    public function treatmentPlanItem()
    {
        return $this->hasOne(\App\Models\TreatmentPlanItem::class);
    }
}
