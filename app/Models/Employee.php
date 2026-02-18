<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Employee extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        "name",
        "email",
        "password",
        "salary",
    ];
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
