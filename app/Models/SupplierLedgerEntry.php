<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class SupplierLedgerEntry extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;
    public static $hasBranchColumn = true; // ✅ تفعيل BranchScope

    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    // ✅ الثوابت
    const TYPE_PURCHASE = 'purchase';
    const TYPE_PAYMENT = 'payment';
    const TYPE_PURCHASE_RETURN = 'purchase_return';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'purchase_order_id',
        'supplier_payment_id',
        'type',
        'debit',
        'credit',
        'entry_date',
        'description',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function payment()
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    // ============ Scopes ============

    public function scopeForSupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('entry_date', [$from, $to]);
    }

    public function scopeBeforeDate($query, $date)
    {
        return $query->where('entry_date', '<', $date);
    }

    // ============ Accessors ============

    public function getNetAmountAttribute(): float
    {
        return $this->debit - $this->credit;
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_PURCHASE => 'Purchase',
            self::TYPE_PAYMENT => 'Payment',
            self::TYPE_PURCHASE_RETURN => 'Purchase Return',
            default => $this->type,
        };
    }

    // ============ Helpers ============

    public function isDebit(): bool
    {
        return $this->debit > 0;
    }

    public function isCredit(): bool
    {
        return $this->credit > 0;
    }

    public function isPurchase(): bool
    {
        return $this->type === self::TYPE_PURCHASE;
    }

    public function isPayment(): bool
    {
        return $this->type === self::TYPE_PAYMENT;
    }

    public function isPurchaseReturn(): bool
    {
        return $this->type === self::TYPE_PURCHASE_RETURN;
    }
}
