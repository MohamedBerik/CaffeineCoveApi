<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class UserAlertPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'alert_code',
        'enabled',
    ];
}
