<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class PurchaseOrderItem extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'product_id',
        'quantity',
        'unit_cost',
        'total'
    ];
    public function product()
    {
        return $this->belongsTo(Product::class)
            ->where('company_id', $this->company_id);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
