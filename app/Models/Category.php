<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        "id",
        "cate_image",
        "title_en",
        "title_ar",
        "description_en",
        "description_ar",

    ];
}
