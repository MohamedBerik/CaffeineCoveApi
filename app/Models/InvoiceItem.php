<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

class InvoiceItem extends Model
{
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
        return $this->belongsTo(Product::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
