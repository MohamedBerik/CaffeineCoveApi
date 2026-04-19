<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Supplier extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix
    public static $hasCompanyColumn = true;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'address',
        'contact_person',
        'notes',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(SupplierLedgerEntry::class);
    }

    // ============ Scopes ============

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    // ============ Accessors ============

    public function getTotalPurchasesAttribute(): float
    {
        return $this->purchaseOrders()
            ->where('status', '!=', PurchaseOrder::STATUS_CANCELLED)
            ->sum('total');
    }

    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return $this->total_purchases - $this->total_paid;
    }

    public function getPurchasesCountAttribute(): int
    {
        return $this->purchaseOrders()->count();
    }

    public function getPendingOrdersCountAttribute(): int
    {
        return $this->purchaseOrders()
            ->whereIn('status', ['ordered', 'partially_paid'])
            ->count();
    }

    // ============ Helpers ============

    public function hasBalance(): bool
    {
        return $this->balance > 0;
    }

    public function hasOverpayment(): bool
    {
        return $this->balance < 0;
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance, 2);
    }

    public function getStatusAttribute(): string
    {
        if ($this->balance > 0) {
            return 'owed';
        } elseif ($this->balance < 0) {
            return 'overpaid';
        }
        return 'settled';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'owed' => 'مستحق عليه',
            'overpaid' => 'له رصيد',
            default => 'متوازن',
        };
    }
}
