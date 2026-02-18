<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Reservation extends Model
{
    use BelongsToCompanyTrait;

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
