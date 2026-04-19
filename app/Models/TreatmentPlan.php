<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class TreatmentPlan extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix
    protected static $hasCompanyColumn = true;

    // ✅ الثوابت
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'customer_id',
        'title',
        'notes',
        'total_cost',
        'status',
    ];

    protected $casts = [
        'total_cost' => 'decimal:2',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'total_cost' => 0,
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'treatment_plan_id');
    }

    public function items()
    {
        return $this->hasMany(TreatmentPlanItem::class, 'treatment_plan_id');
    }

    // ============ Scopes ============

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    // ============ Accessors ============

    public function getTotalPaidAttribute(): float
    {
        return $this->invoices()
            ->where('status', Invoice::STATUS_PAID)
            ->sum('total');
    }

    public function getTotalInvoicedAttribute(): float
    {
        return $this->invoices()->sum('total');
    }

    public function getRemainingAttribute(): float
    {
        return max(0, $this->total_cost - $this->total_paid);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_cost <= 0) {
            return 0;
        }
        return min(100, ($this->total_paid / $this->total_cost) * 100);
    }

    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    public function getCompletedItemsCountAttribute(): int
    {
        return $this->items()->where('status', TreatmentPlanItem::STATUS_COMPLETED)->count();
    }

    public function getInvoicesCountAttribute(): int
    {
        return $this->invoices()->count();
    }

    // ============ Helpers ============

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isFullyPaid(): bool
    {
        return $this->remaining <= 0;
    }

    public function canBeModified(): bool
    {
        return $this->isActive();
    }

    public function recalculateTotal(): void
    {
        $total = $this->items()->sum('price');
        $this->update(['total_cost' => $total]);
    }

    public function markAsCompleted(): void
    {
        if ($this->isActive()) {
            $this->update(['status' => self::STATUS_COMPLETED]);
        }
    }

    public function cancel(): void
    {
        if ($this->isActive()) {
            $this->update(['status' => self::STATUS_CANCELLED]);
        }
    }

    // ============ Boot ============

    protected static function booted()
    {
        static::created(function ($plan) {
            ActivityLog::create([
                'company_id' => $plan->company_id,
                'user_id' => auth()->id(),
                'action' => 'treatment_plan.created',
                'subject_type' => TreatmentPlan::class,
                'subject_id' => $plan->id,
                'properties' => [
                    'customer_id' => $plan->customer_id,
                    'title' => $plan->title,
                ]
            ]);
        });
    }
}
