<?php

namespace App\Models;

use App\Events\AlertCreated;
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
        // دالة موحدة للـ created
        static::created(function ($alert) {
            // 1. تسجيل في ActivityLog
            ActivityLog::create([
                'company_id' => $alert->company_id,
                'user_id' => auth()->id(),
                'action' => 'created',
                'subject_type' => 'SystemAlert',
                'subject_id' => $alert->id,
                'properties' => [
                    'new' => $alert->toArray()
                ]
            ]);

            // 2. Broadcasting للإشعارات
            broadcast(new AlertCreated($alert))->toOthers();
        });

        static::updated(function ($alert) {
            ActivityLog::create([
                'company_id' => $alert->company_id,
                'user_id' => auth()->id(),
                'action' => 'updated',
                'subject_type' => 'SystemAlert',
                'subject_id' => $alert->id,
                'properties' => [
                    'old' => $alert->getOriginal(),
                    'changes' => $alert->getChanges()
                ]
            ]);
        });

        static::deleted(function ($alert) {
            ActivityLog::create([
                'company_id' => $alert->company_id,
                'user_id' => auth()->id(),
                'action' => 'deleted',
                'subject_type' => 'SystemAlert',
                'subject_id' => $alert->id,
            ]);
        });
    }
}
