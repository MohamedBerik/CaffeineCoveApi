<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Appointment extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // public static $hasCompanyColumn = true;

    // ✅ الثوابت
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NO_SHOW = 'no_show';

    const TYPE_CONSULTATION = 'consultation';
    const TYPE_TREATMENT = 'treatment';
    const TYPE_FOLLOW_UP = 'follow_up';
    const TYPE_EMERGENCY = 'emergency';

    const REMINDER_PENDING = 'pending';
    const REMINDER_PROCESSING = 'processing';
    const REMINDER_SENT = 'sent';
    const REMINDER_SKIPPED = 'skipped';
    const REMINDER_FAILED = 'failed';

    const FOLLOW_UP_PENDING = 'pending';
    const FOLLOW_UP_PROCESSING = 'processing';
    const FOLLOW_UP_SENT = 'sent';
    const FOLLOW_UP_RETRYING = 'retrying';
    const FOLLOW_UP_SKIPPED = 'skipped';
    const FOLLOW_UP_STOPPED = 'stopped';

    protected $fillable = [
        'company_id',
        'branch_id',
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
        'reminder_stage',
        'reminder_dedup_key',
        'last_reminder_at',
        'next_reminder_at',
        'reminder_sent_count',
        'reminder_retry_count',
        'reminder_last_attempt_at',
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
        'reminder_retry_count' => 'integer',
        'reminder_last_attempt_at' => 'datetime',
        'follow_up_at' => 'datetime',
        'follow_up_sent_at' => 'datetime',
        'follow_up_retry_count' => 'integer',
        'follow_up_next_retry_at' => 'datetime',
    ];

    protected $appends = [
        'is_upcoming',
        'is_past',
        'can_be_modified',
        'status_label',   // ✅ جديد
        'status_color',   // ✅ جديد
    ];

    // ============ Relationships ============

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

    public function treatmentPlanItem()
    {
        return $this->hasOne(TreatmentPlanItem::class);
    }

    public function dentalRecords()
    {
        return $this->hasMany(DentalRecord::class);
    }

    // ============ Scopes ============

    public function scopeScheduled($query)
    {
        return $query->whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_CONFIRMED]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeNoShow($query)
    {
        return $query->where('status', self::STATUS_NO_SHOW);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('appointment_date', today());
    }

    public function scopeTomorrow($query)
    {
        return $query->whereDate('appointment_date', today()->addDay());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('appointment_date', [
            today()->startOfWeek(),
            today()->endOfWeek()
        ]);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereDate('appointment_date', '>=', today())
            ->whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_CONFIRMED]);
    }

    public function scopeForDoctor($query, $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function scopeForPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeNeedsReminder($query)
    {
        return $query->whereIn('reminder_status', [self::REMINDER_PENDING, self::REMINDER_PROCESSING])
            ->where('next_reminder_at', '<=', now());
    }

    public function scopeNeedsFollowUp($query)
    {
        return $query->where('status', self::STATUS_COMPLETED)
            ->whereIn('follow_up_state', [self::FOLLOW_UP_PENDING, self::FOLLOW_UP_RETRYING])
            ->where('follow_up_at', '<=', now());
    }

    // ============ Accessors ============

    public function getAppointmentTimeAttribute($value)
    {
        if (!$value) return null;
        return Carbon::parse($value)->format('H:i');
    }

    public function getIsUpcomingAttribute(): bool
    {
        if (!$this->appointment_date) return false;

        $appointmentDateTime = Carbon::parse($this->appointment_date->format('Y-m-d') . ' ' . $this->appointment_time);
        return $appointmentDateTime->isFuture() &&
            in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_CONFIRMED]);
    }

    public function getIsPastAttribute(): bool
    {
        if (!$this->appointment_date) return false;

        $appointmentDateTime = Carbon::parse($this->appointment_date->format('Y-m-d') . ' ' . $this->appointment_time);
        return $appointmentDateTime->isPast();
    }

    public function getCanBeModifiedAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_CONFIRMED])
            && !$this->is_past;
    }

    public function getDurationAttribute(): int
    {
        return match ($this->appointment_type) {
            self::TYPE_CONSULTATION => 30,
            self::TYPE_TREATMENT => 60,
            self::TYPE_FOLLOW_UP => 15,
            self::TYPE_EMERGENCY => 45,
            default => 30,
        };
    }

    public function getFormattedDateTimeAttribute(): string
    {
        $date = $this->appointment_date->format('Y-m-d');
        return "{$date} {$this->appointment_time}";
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_NO_SHOW => 'No Show',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SCHEDULED => 'blue',
            self::STATUS_CONFIRMED => 'green',
            self::STATUS_IN_PROGRESS => 'orange',
            self::STATUS_COMPLETED => 'gray',
            self::STATUS_CANCELLED => 'red',
            self::STATUS_NO_SHOW => 'red',
            default => 'gray',
        };
    }

    // ============ Helpers ============

    public function isScheduled(): bool
    {
        return in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_CONFIRMED]);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isNoShow(): bool
    {
        return $this->status === self::STATUS_NO_SHOW;
    }

    public function markAsConfirmed(): void
    {
        $this->update(['status' => self::STATUS_CONFIRMED]);
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }

    public function markAsCancelled(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    public function markAsNoShow(): void
    {
        $this->update(['status' => self::STATUS_NO_SHOW]);
    }

    // ============ Boot - Activity Logging ============

    protected static function booted()
    {
        static::created(function ($appointment) {
            if (auth()->check()) {
                ActivityLog::create([
                    'company_id' => $appointment->company_id,
                    'user_id' => auth()->id(),
                    'action' => 'appointment.created',
                    'subject_type' => Appointment::class,
                    'subject_id' => $appointment->id,
                    'properties' => [
                        'patient_id' => $appointment->patient_id,
                        'doctor_id' => $appointment->doctor_id,
                        'appointment_date' => $appointment->appointment_date,
                        'appointment_time' => $appointment->appointment_time,
                        'status' => $appointment->status,
                    ]
                ]);
            }
        });

        static::updated(function ($appointment) {
            if (auth()->check()) {
                $changes = $appointment->getChanges();
                unset($changes['updated_at']);

                if (!empty($changes)) {
                    ActivityLog::create([
                        'company_id' => $appointment->company_id,
                        'user_id' => auth()->id(),
                        'action' => 'appointment.updated',
                        'subject_type' => Appointment::class,
                        'subject_id' => $appointment->id,
                        'properties' => [
                            'changes' => $changes,
                            'old' => array_intersect_key($appointment->getOriginal(), $changes),
                        ]
                    ]);
                }
            }
        });

        static::deleted(function ($appointment) {
            if (auth()->check()) {
                ActivityLog::create([
                    'company_id' => $appointment->company_id,
                    'user_id' => auth()->id(),
                    'action' => 'appointment.deleted',
                    'subject_type' => Appointment::class,
                    'subject_id' => $appointment->id,
                    'properties' => [
                        'patient_id' => $appointment->patient_id,
                        'doctor_id' => $appointment->doctor_id,
                    ]
                ]);
            }
        });
    }
}
