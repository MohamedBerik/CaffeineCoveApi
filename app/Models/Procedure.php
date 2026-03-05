<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Procedure extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'name',
        'default_price',
        'is_active',
    ];
}
