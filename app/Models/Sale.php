<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'company_id',
        "title_en",
        "title_ar",
        "description_en",
        "description_ar",
        "price",
        "quantity",
        "employee_id",
    ];
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
