<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class PurchaseOrder extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'supplier_id',
        'number',
        'total',
        'status'
    ];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
