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
        return $this->hasMany(PurchaseOrderItem::class)
            ->where('company_id', $this->company_id);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)
            ->where('company_id', $this->company_id);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class)
            ->where('company_id', $this->company_id);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
