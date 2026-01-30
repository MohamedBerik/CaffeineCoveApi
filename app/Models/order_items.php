<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class order_items extends Model
{
    protected $fillable = [
        "id",
        "order_id",
        "product_id",
        "quantity",
        "unit_price",
        "total",
    ];
}
