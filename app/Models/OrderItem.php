<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItem extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;
    public static $hasBranchColumn = true; // ✅ تفعيل BranchScope

    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    protected $fillable = [
        'company_id',
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // ============ Boot ============

    protected static function booted()
    {
        static::saving(function ($item) {
            $item->total = $item->quantity * $item->unit_price;
        });

        static::saved(function ($item) {
            if ($item->order) {
                $item->order->recalculateTotal();
            }
        });

        static::deleted(function ($item) {
            if ($item->order) {
                $item->order->recalculateTotal();
            }
        });
    }

    // ============ Helpers ============

    public function getSubtotalAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }
}
