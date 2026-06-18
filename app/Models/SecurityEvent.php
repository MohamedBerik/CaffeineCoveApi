<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'user_id',
        'email',
        'ip',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public $timestamps = false; // نستخدم created_at فقط بدون updated_at
}
