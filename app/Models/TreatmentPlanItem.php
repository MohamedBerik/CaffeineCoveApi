<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class TreatmentPlanItem extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    // ✅ الثوابت
    const STATUS_PLANNED = 'planned';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'company_id',
        'branch_id',

        'treatment_plan_id',
        'procedure_id',
        'procedure',
        'tooth_number',
        'surface',
        'notes',
        'price',
        'status',
        'appointment_id',
        'planned_sessions',
        'completed_sessions',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'planned_sessions' => 'integer',
        'completed_sessions' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PLANNED,
        'planned_sessions' => 1,
        'completed_sessions' => 0,
    ];

    protected $appends = [
        'remaining_sessions',
        'is_completed',
        'progress_percentage',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function plan()
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id');
    }

    public function procedureRef()
    {
        return $this->belongsTo(Procedure::class, 'procedure_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function dentalRecord()
    {
        return $this->hasOne(DentalRecord::class, 'treatment_plan_item_id');
    }

    // ============ Scopes ============

    public function scopePlanned($query)
    {
        return $query->where('status', self::STATUS_PLANNED);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForPlan($query, $planId)
    {
        return $query->where('treatment_plan_id', $planId);
    }

    public function scopeForTooth($query, string $toothNumber)
    {
        return $query->where('tooth_number', $toothNumber);
    }

    // ============ Accessors ============

    public function getRemainingSessionsAttribute(): int
    {
        $planned = (int) ($this->planned_sessions ?? 1);
        $completed = (int) ($this->completed_sessions ?? 0);
        return max($planned - $completed, 0);
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED || $this->remaining_sessions === 0;
    }

    public function getProgressPercentageAttribute(): float
    {
        $planned = max(1, (int) ($this->planned_sessions ?? 1));
        $completed = (int) ($this->completed_sessions ?? 0);
        return min(100, ($completed / $planned) * 100);
    }

    public function getTotalPriceAttribute(): float
    {
        return $this->price * ($this->planned_sessions ?? 1);
    }

    public function getToothWithSurfaceAttribute(): string
    {
        if ($this->surface) {
            return $this->tooth_number . ' (' . $this->surface . ')';
        }
        return $this->tooth_number ?: 'N/A';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PLANNED => 'Planned',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            default => 'Unknown',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PLANNED => 'blue',
            self::STATUS_IN_PROGRESS => 'orange',
            self::STATUS_COMPLETED => 'green',
            default => 'gray',
        };
    }

    // ============ Helpers ============

    public function isPlanned(): bool
    {
        return $this->status === self::STATUS_PLANNED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->is_completed;
    }

    public function canBeStarted(): bool
    {
        return $this->isPlanned() && $this->remaining_sessions > 0;
    }

    public function canBeCompleted(): bool
    {
        return $this->isInProgress() || ($this->isPlanned() && $this->remaining_sessions === 0);
    }

    public function start(int $appointmentId): void
    {
        if ($this->canBeStarted()) {
            $this->update([
                'status' => self::STATUS_IN_PROGRESS,
                'appointment_id' => $appointmentId,
                'started_at' => now(),
            ]);
        }
    }

    public function complete(): void
    {
        if ($this->canBeCompleted()) {
            $this->update([
                'status' => self::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            // تحديث خطة العلاج
            if ($this->plan) {
                $this->plan->recalculateTotal();
            }
        }
    }

    public function incrementCompletedSessions(): void
    {
        $newCompleted = min($this->completed_sessions + 1, $this->planned_sessions);
        $newStatus = $newCompleted >= $this->planned_sessions
            ? self::STATUS_COMPLETED
            : self::STATUS_IN_PROGRESS;

        $this->update([
            'completed_sessions' => $newCompleted,
            'status' => $newStatus,
            'completed_at' => $newStatus === self::STATUS_COMPLETED ? now() : null,
        ]);
    }

    // ============ Boot ============

    protected static function booted()
    {
        static::creating(function ($item) {
            if (!$item->status) {
                $item->status = self::STATUS_PLANNED;
            }
            if (!$item->planned_sessions) {
                $item->planned_sessions = 1;
            }
        });

        static::saved(function ($item) {
            if ($item->plan) {
                $item->plan->recalculateTotal();
            }
        });

        static::deleted(function ($item) {
            if ($item->plan) {
                $item->plan->recalculateTotal();
            }
        });
    }
}
