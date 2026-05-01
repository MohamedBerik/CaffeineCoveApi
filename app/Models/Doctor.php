<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Doctor extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;
    public static $hasBranchColumn = true; // ✅ تفعيل BranchScope

    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    protected $fillable = [
        'company_id',
        'name',
        'phone',
        'email',
        'is_active',
        'work_start',
        'work_end',
        'slot_minutes',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'slot_minutes' => 'integer',
    ];

    protected $attributes = [
        'work_start' => '09:00',
        'work_end' => '21:00',
        'slot_minutes' => 30,
        'is_active' => true,
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function availabilities()
    {
        return $this->hasMany(DoctorAvailability::class);
    }

    public function dentalRecords()
    {
        return $this->hasMany(DentalRecord::class);
    }

    // ============ Scopes ============

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    // ============ Helpers ============

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function getWorkingHoursAttribute(): string
    {
        return substr($this->work_start, 0, 5) . ' - ' . substr($this->work_end, 0, 5);
    }

    public function getAppointmentsCountAttribute(): int
    {
        return $this->appointments()->count();
    }

    public function getTodayAppointmentsCountAttribute(): int
    {
        return $this->appointments()
            ->whereDate('appointment_date', today())
            ->count();
    }

    public function getUpcomingAppointmentsCountAttribute(): int
    {
        return $this->appointments()
            ->whereDate('appointment_date', '>=', today())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->count();
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
