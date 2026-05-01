<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class PurchaseOrder extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    // ✅ الثوابت
    const STATUS_ORDERED = 'ordered';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_PAID = 'paid';
    const STATUS_RECEIVED = 'received';
    const STATUS_HAS_RETURN = 'has_return';
    const STATUS_RETURNED = 'returned';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'number',
        'total',
        'status',
        'received_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_ORDERED,
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(SupplierLedgerEntry::class);
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    // ============ Scopes ============

    public function scopeOrdered($query)
    {
        return $query->where('status', self::STATUS_ORDERED);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeReceived($query)
    {
        return $query->whereNotNull('received_at');
    }

    public function scopeNotReceived($query)
    {
        return $query->whereNull('received_at');
    }

    public function scopeForSupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    // ============ Accessors ============

    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    public function getRemainingAttribute(): float
    {
        return max(0, $this->total - $this->total_paid);
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->remaining <= 0;
    }

    public function getIsReceivedAttribute(): bool
    {
        return !is_null($this->received_at);
    }

    // ============ Helpers ============

    public function isOrdered(): bool
    {
        return $this->status === self::STATUS_ORDERED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_PAID;
    }

    public function isReceived(): bool
    {
        return !is_null($this->received_at);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canBeModified(): bool
    {
        return !$this->isReceived() && !$this->isCancelled();
    }

    public function canReceiveItems(): bool
    {
        return !$this->isReceived() && !$this->isCancelled();
    }

    public function updatePaymentStatus(): void
    {
        $totalPaid = $this->total_paid;

        if ($totalPaid <= 0) {
            $status = self::STATUS_ORDERED;
        } elseif ($totalPaid < $this->total) {
            $status = self::STATUS_PARTIALLY_PAID;
        } else {
            $status = self::STATUS_PAID;
        }

        $this->update(['status' => $status]);
    }

    public function markAsReceived(): void
    {
        if ($this->canReceiveItems()) {
            $this->update([
                'received_at' => now(),
                'status' => self::STATUS_RECEIVED,
            ]);
        }
    }

    public function cancel(): void
    {
        if (!$this->isReceived()) {
            $this->update(['status' => self::STATUS_CANCELLED]);
        }
    }
}
