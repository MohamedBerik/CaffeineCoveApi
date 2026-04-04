<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Support\Facades\Cache;

class SystemAlert extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'code',
        'type',
        'priority',
        'message',
        'meta',
        'triggered_at',
        'resolved_at',
        'acknowledged_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'triggered_at' => 'datetime',
        'resolved_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::saved(function ($alert) {
            Cache::forget("dashboard_{$alert->company_id}");
        });
    }
}
