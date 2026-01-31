<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    protected $fillable = [
        'supplier_id',
        'purchase_order_id',
        'amount',
        'method',
        'paid_at',
        'paid_by'
    ];
}
