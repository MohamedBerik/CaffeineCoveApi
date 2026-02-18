<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class OrderItem extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'total',
    ];

    // =======================
    // Relations
    // =======================

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
