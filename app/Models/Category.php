<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Category extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    /**
     * ✅ Performance fix
     */
    // public static $hasCompanyColumn = true;

    protected $fillable = [
        'company_id',
        'branch_id',
        'cate_image',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
    ];

    protected $appends = [
        'title',
        'description',
    ];

    /**
     * Relationship: Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relationship: Products in this category
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get localized title based on app locale
     */
    public function getTitleAttribute(): string
    {
        return app()->getLocale() === 'ar'
            ? ($this->title_ar ?: $this->title_en)
            : ($this->title_en ?: $this->title_ar);
    }

    /**
     * Get localized description based on app locale
     */
    public function getDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar'
            ? ($this->description_ar ?: $this->description_en)
            : ($this->description_en ?: $this->description_ar);
    }

    /**
     * Get image URL
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->cate_image) {
            return null;
        }
        return asset('/img/category/' . $this->cate_image);
    }

    /**
     * Scope: Active categories (has products)
     */
    public function scopeHasProducts($query)
    {
        return $query->whereHas('products');
    }

    /**
     * Scope: Search by name
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title_en', 'like', "%{$term}%")
                ->orWhere('title_ar', 'like', "%{$term}%");
        });
    }

    /**
     * Get products count
     */
    public function getProductsCountAttribute(): int
    {
        return $this->products()->count();
    }
}
