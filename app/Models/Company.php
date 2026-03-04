<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'status',
        'trial_ends_at',
        'branding',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'branding' => 'array',
    ];
}
