<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{

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
