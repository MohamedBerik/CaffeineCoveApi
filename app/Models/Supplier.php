<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Supplier extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = ['company_id', 'name', 'email', 'phone'];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
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
