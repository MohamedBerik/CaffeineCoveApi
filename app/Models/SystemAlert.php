<?php

namespace App\Models;

use App\Events\AlertCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class SystemAlert extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    // ✅ الثوابت
    const TYPE_STOCK = 'stock';
    const TYPE_PAYMENT = 'payment';
    const TYPE_APPOINTMENT = 'appointment';
    const TYPE_SYSTEM = 'system';
    const TYPE_TRIAL = 'trial';
    const TYPE_SECURITY = 'security';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_CRITICAL = 'critical';

    protected $fillable = [
        'company_id',
        'branch_id',
        'user_id',
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

    protected $attributes = [
        'triggered_at' => null,
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ============ Scopes ============

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_at');
    }

    public function scopeAcknowledged($query)
    {
        return $query->whereNotNull('acknowledged_at');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('acknowledged_at');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_CRITICAL]);
    }

    // ============ Accessors ============

    public function getIsReadAttribute(): bool
    {
        return !is_null($this->acknowledged_at);
    }

    public function getIsResolvedAttribute(): bool
    {
        return !is_null($this->resolved_at);
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_CRITICAL => 'Critical',
            default => 'Unknown',
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            self::PRIORITY_LOW => 'gray',
            self::PRIORITY_MEDIUM => 'blue',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_CRITICAL => 'red',
            default => 'gray',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_STOCK => 'Stock Alert',
            self::TYPE_PAYMENT => 'Payment Alert',
            self::TYPE_APPOINTMENT => 'Appointment Alert',
            self::TYPE_SYSTEM => 'System Alert',
            self::TYPE_TRIAL => 'Trial Alert',
            self::TYPE_SECURITY => 'Security Alert',
            default => 'Alert',
        };
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->triggered_at?->diffForHumans() ?? '';
    }

    // ============ Helpers ============

    public function acknowledge(): void
    {
        if (!$this->acknowledged_at) {
            $this->update(['acknowledged_at' => now()]);
        }
    }

    public function resolve(): void
    {
        if (!$this->resolved_at) {
            $this->update(['resolved_at' => now()]);
        }
    }

    public function isRead(): bool
    {
        return $this->is_read;
    }

    public function isUnread(): bool
    {
        return !$this->is_read;
    }

    public function isResolved(): bool
    {
        return $this->is_resolved;
    }

    public function isUnresolved(): bool
    {
        return !$this->is_resolved;
    }

    // ============ Static Helpers ============

    public static function createAlert(
        int $companyId,
        string $message,
        string $type = self::TYPE_SYSTEM,
        string $priority = self::PRIORITY_MEDIUM,
        array $meta = [],
        ?string $code = null
    ): self {
        return static::create([
            'company_id' => $companyId,
            'code' => $code,
            'type' => $type,
            'priority' => $priority,
            'message' => $message,
            'meta' => $meta,
            'triggered_at' => now(),
        ]);
    }

    public static function markAllAsRead(int $companyId): void
    {
        static::where('company_id', $companyId)
            ->whereNull('acknowledged_at')
            ->update(['acknowledged_at' => now()]);
    }

    // ============ Boot ============

    protected static function booted()
    {
        static::creating(function ($alert) {
            if (!$alert->triggered_at) {
                $alert->triggered_at = now();
            }
        });

        static::created(function ($alert) {
            ActivityLog::create([
                'company_id' => $alert->company_id,
                'branch_id' => $alert->branch_id,
                'user_id' => auth()->id(),
                'action' => 'alert.created',
                'subject_type' => SystemAlert::class,
                'subject_id' => $alert->id,
                'properties' => [
                    'type' => $alert->type,
                    'priority' => $alert->priority,
                    'message' => $alert->message,
                ]
            ]);

            broadcast(new AlertCreated($alert))->toOthers();
        });

        static::updated(function ($alert) {
            if (auth()->check()) {
                $changes = $alert->getChanges();
                unset($changes['updated_at']);

                if (!empty($changes)) {
                    ActivityLog::create([
                        'company_id' => $alert->company_id,
                        'branch_id' => $alert->branch_id,
                        'user_id' => auth()->id(),
                        'action' => 'alert.updated',
                        'subject_type' => SystemAlert::class,
                        'subject_id' => $alert->id,
                        'properties' => ['changes' => $changes]
                    ]);
                }
            }
        });
    }
}
