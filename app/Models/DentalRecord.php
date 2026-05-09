<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class DentalRecord extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    // ✅ الثوابت
    const STATUS_PLANNED = 'planned';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'appointment_id',
        'doctor_id',
        'procedure_id',
        'tooth_number',
        'surface',
        'status',
        'notes',
        'treatment_plan_item_id',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

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
        return $this->belongsTo(Procedure::class)
            ->withoutGlobalScope(\App\Models\Concerns\BranchScope::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function treatmentPlanItem()
    {
        return $this->belongsTo(TreatmentPlanItem::class);
    }

    // ============ Scopes ============

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForTooth($query, string $toothNumber)
    {
        return $query->where('tooth_number', $toothNumber);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    // ============ Helpers ============

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isPlanned(): bool
    {
        return $this->status === self::STATUS_PLANNED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }

    public function getToothWithSurfaceAttribute(): string
    {
        if ($this->surface) {
            return $this->tooth_number . ' (' . $this->surface . ')';
        }
        return $this->tooth_number;
    }
}
