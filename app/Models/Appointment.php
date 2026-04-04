<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;

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
        'reminder_status',
        'last_reminder_at',
        'next_reminder_at',
        'reminder_sent_count',
        'follow_up_status',
        'follow_up_state',
        'follow_up_at',
        'follow_up_sent_at',
        'follow_up_retry_count',
        'follow_up_next_retry_at',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'doctor_id' => 'integer',
        'patient_id' => 'integer',
        'last_reminder_at' => 'datetime',
        'next_reminder_at' => 'datetime',
        'reminder_sent_count' => 'integer',
        'follow_up_at' => 'datetime',
        'follow_up_sent_at' => 'datetime',
        'follow_up_retry_count' => 'integer',
        'follow_up_next_retry_at' => 'datetime',
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
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function getAppointmentTimeAttribute($value)
    {
        if (!$value) return null;
        return Carbon::parse($value)->format('H:i');
    }

    public function treatmentPlanItem()
    {
        return $this->hasOne(\App\Models\TreatmentPlanItem::class);
    }

    protected static function booted()
    {
        static::created(function ($appointment) {
            ActivityLog::create([
                'company_id' => $appointment->company_id,
                'user_id' => auth()->id(),
                'action' => 'created',
                'subject_type' => 'Appointment',
                'subject_id' => $appointment->id,
                'properties' => [
                    'new' => $appointment->toArray()
                ]
            ]);
        });

        static::updated(function ($appointment) {
            ActivityLog::create([
                'company_id' => $appointment->company_id,
                'user_id' => auth()->id(),
                'action' => 'updated',
                'subject_type' => 'Appointment',
                'subject_id' => $appointment->id,
                'properties' => [
                    'old' => $appointment->getOriginal(),
                    'changes' => $appointment->getChanges()
                ]
            ]);
        });

        static::deleted(function ($appointment) {
            ActivityLog::create([
                'company_id' => $appointment->company_id,
                'user_id' => auth()->id(),
                'action' => 'deleted',
                'subject_type' => 'Appointment',
                'subject_id' => $appointment->id,
            ]);
        });
    }
}
