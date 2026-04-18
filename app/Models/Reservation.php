<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Reservation extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix
    // protected static $hasCompanyColumn = true;

    // ✅ الثوابت
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';
    const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'persons',
        'status',
        'date',
        'time',
        'message',
    ];

    protected $casts = [
        'date' => 'date',
        'persons' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // ============ Scopes ============

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    public function scopeUpcoming($query)
    {
        return $query->whereDate('date', '>=', today())
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function scopePast($query)
    {
        return $query->whereDate('date', '<', today());
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    // ============ Accessors ============

    public function getDateTimeAttribute(): string
    {
        return $this->date->format('Y-m-d') . ' ' . substr($this->time, 0, 5);
    }

    public function getIsUpcomingAttribute(): bool
    {
        return $this->date->isFuture() &&
            in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function getIsPastAttribute(): bool
    {
        return $this->date->isPast();
    }

    // ============ Helpers ============

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isNoShow(): bool
    {
        return $this->status === self::STATUS_NO_SHOW;
    }

    public function canBeModified(): bool
    {
        return $this->isPending() && $this->is_upcoming;
    }

    public function confirm(): void
    {
        if ($this->isPending()) {
            $this->update(['status' => self::STATUS_CONFIRMED]);
        }
    }

    public function cancel(): void
    {
        if (in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED])) {
            $this->update(['status' => self::STATUS_CANCELLED]);
        }
    }

    public function markAsCompleted(): void
    {
        if ($this->isConfirmed()) {
            $this->update(['status' => self::STATUS_COMPLETED]);
        }
    }

    public function markAsNoShow(): void
    {
        if ($this->isConfirmed()) {
            $this->update(['status' => self::STATUS_NO_SHOW]);
        }
    }
}
