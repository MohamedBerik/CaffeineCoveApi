<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Supply extends Model
{
    use HasFactory, BelongsToCompanyTrait;
    public static $hasBranchScope = true;


    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'sku',
        'unit_cost',
        'stock_quantity',
        'category_id',
        'supplier_id',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'stock_quantity' => 'integer',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // Scopes
    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "%{$term}%");
    }

    // Accessors
    public function getInventoryValueAttribute(): float
    {
        return $this->stock_quantity * $this->unit_cost;
    }
}
