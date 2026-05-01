<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;
    public static $hasBranchColumn = true; // ✅ تفعيل BranchScope

    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    // ✅ الثوابت
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'customer_id',
        'status',
        'total',
        'created_by',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
    ];

    protected $attributes = [
        'title_en' => 'ERP Order',
        'title_ar' => 'طلب ERP',
        'description_en' => '',
        'description_ar' => '',
        'status' => self::STATUS_PENDING,
    ];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
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

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
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

    public function canBeModified(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeConfirmed(): bool
    {
        return $this->status === self::STATUS_PENDING && !$this->invoice;
    }

    public function confirm(): void
    {
        if ($this->canBeConfirmed()) {
            $this->update(['status' => self::STATUS_CONFIRMED]);
        }
    }

    public function cancel(): void
    {
        if ($this->status !== self::STATUS_CANCELLED) {
            $this->update(['status' => self::STATUS_CANCELLED]);
        }
    }

    public function recalculateTotal(): void
    {
        $total = $this->items()->sum('total');
        $this->update(['total' => $total]);
    }

    public function getTitleAttribute(): string
    {
        return app()->getLocale() === 'ar'
            ? ($this->title_ar ?: $this->title_en)
            : ($this->title_en ?: $this->title_ar);
    }

    public function getDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar'
            ? ($this->description_ar ?: $this->description_en)
            : ($this->description_en ?: $this->description_ar);
    }
}
