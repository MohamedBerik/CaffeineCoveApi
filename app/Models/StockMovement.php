<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class StockMovement extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'product_id',
        'type',
        'quantity',
        'reference_type',
        'reference_id',
        'created_by'
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
