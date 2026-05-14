<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class PurchaseOrderItem extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'branch_id',
        'purchase_order_id',
        'supply_id',            // ✅ تم التغيير
        'quantity',
        'unit_cost',
        'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supply()
    {
        return $this->belongsTo(Supply::class);   // ✅ العلاقة مع Supply
    }

    // ============ Boot ============

    protected static function booted()
    {
        static::saving(function ($item) {
            $item->total = $item->quantity * $item->unit_cost;
        });

        static::saved(function ($item) {
            if ($item->purchaseOrder) {
                $item->purchaseOrder->update(['total' => $item->purchaseOrder->items()->sum('total')]);
            }
        });

        static::deleted(function ($item) {
            if ($item->purchaseOrder) {
                $item->purchaseOrder->update(['total' => $item->purchaseOrder->items()->sum('total')]);
            }
        });
    }

    // ============ Accessors ============

    public function getSubtotalAttribute(): float
    {
        return $this->quantity * $this->unit_cost;
    }

    // ============ Helpers ============

    public function getReceivedQuantityAttribute(): float
    {
        return StockMovement::where('reference_type', PurchaseOrder::class)
            ->where('reference_id', $this->purchase_order_id)
            ->where('supply_id', $this->supply_id)      // ✅ تم التغيير
            ->where('type', StockMovement::TYPE_IN)
            ->sum('quantity');
    }

    public function getReturnedQuantityAttribute(): float
    {
        return StockMovement::where('reference_type', PurchaseOrder::class)
            ->where('reference_id', $this->purchase_order_id)
            ->where('supply_id', $this->supply_id)      // ✅ تم التغيير
            ->where('type', StockMovement::TYPE_OUT)
            ->sum('quantity');
    }

    public function getRemainingQuantityAttribute(): float
    {
        return max(0, $this->received_quantity - $this->returned_quantity);
    }

    public function isFullyReceived(): bool
    {
        return $this->received_quantity >= $this->quantity;
    }
}
