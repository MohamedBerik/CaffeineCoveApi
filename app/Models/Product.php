<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Product extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        "product_image",
        "title_en",
        "title_ar",
        "description_en",
        "description_ar",
        "unit_price",
        "quantity",
        "category_id"
    ];

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class)
            ->where('company_id', $this->company_id);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
