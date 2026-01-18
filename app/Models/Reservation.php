<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        "id",
        "name",
        "email",
        "persons",
        'status',
        "date",
        "time",
        "message",

    ];
}
