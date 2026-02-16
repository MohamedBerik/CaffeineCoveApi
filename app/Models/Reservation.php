<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'company_id',
        "name",
        "email",
        "persons",
        'status',
        "date",
        "time",
        "message",
    ];
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
