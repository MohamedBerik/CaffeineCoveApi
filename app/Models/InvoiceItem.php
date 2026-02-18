<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\Concerns\BelongsToCompanyTrait;

class InvoiceItem extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'product_id',
        'quantity',
        'unit_price',
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
