<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable=[
        "id",
        "title_en",
        "title_ar",
        "description_en",
        "description_ar",
        "price",
        "quantity",
        "customer_id"
    ];
}
