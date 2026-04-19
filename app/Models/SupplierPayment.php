<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class SupplierPayment extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix
    protected static $hasCompanyColumn = true;

    // ✅ الثوابت
    const METHOD_CASH = 'cash';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_CHECK = 'check';
    const METHOD_OTHER = 'other';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'purchase_order_id',
        'amount',
        'method',
        'paid_at',
        'paid_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
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

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function ledgerEntry()
    {
        return $this->hasOne(SupplierLedgerEntry::class, 'supplier_payment_id');
    }

    // ============ Scopes ============

    public function scopeForSupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeForPurchaseOrder($query, $poId)
    {
        return $query->where('purchase_order_id', $poId);
    }

    public function scopeByMethod($query, string $method)
    {
        return $query->where('method', $method);
    }

    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('paid_at', [$from, $to]);
    }

    // ============ Accessors ============

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2);
    }

    public function getMethodLabelAttribute(): string
    {
        return match ($this->method) {
            self::METHOD_CASH => 'Cash',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_CHECK => 'Check',
            self::METHOD_OTHER => 'Other',
            default => $this->method,
        };
    }

    // ============ Helpers ============

    public function isCash(): bool
    {
        return $this->method === self::METHOD_CASH;
    }

    public function isBankTransfer(): bool
    {
        return $this->method === self::METHOD_BANK_TRANSFER;
    }
}
