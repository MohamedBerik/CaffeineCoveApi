<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    // ✅ الثوابت
    const STATUS_TRIAL = 'trial';
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CANCELLED = 'cancelled';

    // ✅ Performance fix - الجدول ده مش بيحتوي company_id
    public static $hasCompanyColumn = false;

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

    // ============ Boot ============
    protected static function booted()
    {
        static::creating(function ($company) {
            if (empty($company->slug)) {
                $company->slug = Str::slug($company->name);
            }

            // ضمان uniqueness
            $originalSlug = $company->slug;
            $count = 1;
            while (static::where('slug', $company->slug)->exists()) {
                $company->slug = $originalSlug . '-' . $count++;
            }

            if (empty($company->status)) {
                $company->status = self::STATUS_TRIAL;
            }
        });
    }

    // ============ Relationships ============
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function settings()
    {
        return $this->hasOne(ClinicSetting::class);
    }

    // ============ Scopes ============
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeTrial($query)
    {
        return $query->where('status', self::STATUS_TRIAL);
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', self::STATUS_SUSPENDED);
    }

    // ============ Helpers ============
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isTrial(): bool
    {
        return $this->status === self::STATUS_TRIAL;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function trialHasExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    public function getTrialDaysLeftAttribute(): ?int
    {
        if (!$this->trial_ends_at) {
            return null;
        }
        return now()->diffInDays($this->trial_ends_at, false);
    }

    public function getLogoAttribute()
    {
        return $this->branding['logo'] ?? null;
    }

    public function getPrimaryColorAttribute()
    {
        return $this->branding['primary_color'] ?? '#1a237e';
    }

    public function getAppNameAttribute()
    {
        return $this->branding['app_name'] ?? $this->name;
    }

    // ============ Actions ============
    public function activate(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    public function suspend(): void
    {
        $this->update([
            'status' => self::STATUS_SUSPENDED,
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }
}
