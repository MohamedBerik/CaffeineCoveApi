<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        "id",
        "product_image",
        "title_en",
        "title_ar",
        "description_en",
        "description_ar",
        "price",
        "quantity",
        "category_id"
    ];
}
