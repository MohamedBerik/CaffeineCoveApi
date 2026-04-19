<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class StockMovement extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    // ✅ الثوابت
    const TYPE_IN = 'in';
    const TYPE_OUT = 'out';

    protected $fillable = [
        'company_id',
        'product_id',
        'type',
        'quantity',
        'reference_type',
        'reference_id',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    // ============ Scopes ============

    public function scopeIn($query)
    {
        return $query->where('type', self::TYPE_IN);
    }

    public function scopeOut($query)
    {
        return $query->where('type', self::TYPE_OUT);
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByReference($query, string $type, int $id)
    {
        return $query->where('reference_type', $type)
            ->where('reference_id', $id);
    }

    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    // ============ Accessors ============

    public function getQuantityFormattedAttribute(): string
    {
        return $this->type === self::TYPE_IN
            ? '+' . $this->quantity
            : '-' . $this->quantity;
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === self::TYPE_IN ? 'Stock In' : 'Stock Out';
    }

    public function getTypeColorAttribute(): string
    {
        return $this->type === self::TYPE_IN ? 'green' : 'red';
    }

    // ============ Helpers ============

    public function isIn(): bool
    {
        return $this->type === self::TYPE_IN;
    }

    public function isOut(): bool
    {
        return $this->type === self::TYPE_OUT;
    }

    public function getReferenceNameAttribute(): ?string
    {
        if (!$this->reference_type || !$this->reference_id) {
            return null;
        }

        $model = app($this->reference_type);
        $record = $model->find($this->reference_id);

        if (!$record) {
            return null;
        }

        return match ($this->reference_type) {
            PurchaseOrder::class => 'PO #' . $record->number,
            Order::class => 'Order #' . $record->id,
            default => class_basename($this->reference_type) . ' #' . $this->reference_id,
        };
    }
}
