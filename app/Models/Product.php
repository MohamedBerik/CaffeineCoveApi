<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Product extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix
    // protected static $hasCompanyColumn = true;

    protected $fillable = [
        'company_id',
        'product_image',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
        'unit_price',
        'stock_quantity',
        'category_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'stock_quantity' => 'integer',
    ];

    protected $appends = [
        'on_hand',
        'title',
        'description',
        'image_url',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    // ============ Scopes ============

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_quantity', '<=', 0);
    }

    public function scopeLowStock($query, int $threshold = 10)
    {
        return $query->where('stock_quantity', '<=', $threshold)
            ->where('stock_quantity', '>', 0);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title_en', 'like', "%{$term}%")
                ->orWhere('title_ar', 'like', "%{$term}%");
        });
    }

    // ============ Accessors ============

    public function getOnHandAttribute(): int
    {
        return (int) $this->stock_quantity;
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

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->product_image) {
            return null;
        }
        return asset('/img/product/' . $this->product_image);
    }

    public function getInventoryValueAttribute(): float
    {
        return $this->stock_quantity * $this->unit_price;
    }

    // ============ Helpers ============

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock_quantity <= 0;
    }

    public function isLowStock(int $threshold = 10): bool
    {
        return $this->stock_quantity > 0 && $this->stock_quantity <= $threshold;
    }

    public function hasEnoughStock(int $quantity): bool
    {
        return $this->stock_quantity >= $quantity;
    }

    public function increaseStock(int $quantity, ?string $reference = null): void
    {
        $this->increment('stock_quantity', $quantity);

        StockMovement::create([
            'company_id' => $this->company_id,
            'product_id' => $this->id,
            'type' => StockMovement::TYPE_IN,
            'quantity' => $quantity,
            'reference' => $reference,
        ]);
    }

    public function decreaseStock(int $quantity, ?string $reference = null): void
    {
        if (!$this->hasEnoughStock($quantity)) {
            throw new \Exception("Insufficient stock for product: {$this->title}");
        }

        $this->decrement('stock_quantity', $quantity);

        StockMovement::create([
            'company_id' => $this->company_id,
            'product_id' => $this->id,
            'type' => StockMovement::TYPE_OUT,
            'quantity' => $quantity,
            'reference' => $reference,
        ]);
    }
}
