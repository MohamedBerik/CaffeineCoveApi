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
        static::created(function ($appointment) {
            ActivityLog::create([
                'company_id' => $appointment->company_id,
                'user_id' => auth()->id(),
                'action' => 'created',
                'subject_type' => 'Appointment',
                'subject_id' => $appointment->id,
                'properties' => [
                    'new' => $appointment->toArray()
                ]
            ]);
        });

        static::updated(function ($appointment) {
            ActivityLog::create([
                'company_id' => $appointment->company_id,
                'user_id' => auth()->id(),
                'action' => 'updated',
                'subject_type' => 'Appointment',
                'subject_id' => $appointment->id,
                'properties' => [
                    'old' => $appointment->getOriginal(),
                    'changes' => $appointment->getChanges()
                ]
            ]);
        });

        static::deleted(function ($appointment) {
            ActivityLog::create([
                'company_id' => $appointment->company_id,
                'user_id' => auth()->id(),
                'action' => 'deleted',
                'subject_type' => 'Appointment',
                'subject_id' => $appointment->id,
            ]);
        });
    }
}
