<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory;
    use HasApiTokens, Notifiable;
    use BelongsToCompanyTrait;
    use HasRoles;

    // ✅ Performance fix - تفعيل
    public static $hasCompanyColumn = true;
    protected $guard_name = 'api';

    // ✅ الثوابت
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_ADMIN = 'admin';
    const ROLE_DOCTOR = 'doctor';
    const ROLE_RECEPTIONIST = 'receptionist';
    const ROLE_USER = 'user';

    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'email',
        'password',
        'role',
        'status',
        'is_super_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_super_admin'    => 'boolean',
        'status'            => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'is_super_admin' => false,
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function doctorProfile()
    {
        return $this->hasOne(Doctor::class, 'user_id');
    }

    public function createdAppointments()
    {
        return $this->hasMany(Appointment::class, 'created_by');
    }

    public function createdOrders()
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    public function receivedPayments()
    {
        return $this->hasMany(Payment::class, 'received_by');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }


    // ============ Scopes ============

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    public function scopeDoctors($query)
    {
        return $query->where('role', self::ROLE_DOCTOR);
    }

    public function scopeSuperAdmins($query)
    {
        return $query->where('is_super_admin', true);
    }

    public function scopeRegularUsers($query)
    {
        return $query->where('is_super_admin', false);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }

    // ============ Accessors ============

    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_DOCTOR => 'Doctor',
            self::ROLE_RECEPTIONIST => 'Receptionist',
            self::ROLE_USER => 'User',
            default => ucfirst($this->role),
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    // ============ Helpers ============

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isDoctor(): bool
    {
        return $this->role === self::ROLE_DOCTOR;
    }

    public function isReceptionist(): bool
    {
        return $this->role === self::ROLE_RECEPTIONIST;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isAdmin()) {
            return true;
        }

        return false;
    }

    public function mustHaveCompany(): bool
    {
        return !$this->isSuperAdmin();
    }

    public function belongsToCompany(Company $company): bool
    {
        return $this->company_id === $company->id;
    }

    public function canAccessCompany($company): bool
    {
        // Super Admin يقدر يوصل لأي شركة
        if ($this->isSuperAdmin()) {
            return true;
        }

        // لو اتبعت Company object
        if ($company instanceof Company) {
            return $this->company_id === $company->id;
        }

        // لو اتبعت company_id (int)
        $companyId = (int) $company;
        return $this->company_id === $companyId;
    }

    public function activate(): void
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
    }

    public function deactivate(): void
    {
        $this->update(['status' => self::STATUS_INACTIVE]);
    }

    // ============ Boot ============

    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->status) {
                $user->status = self::STATUS_ACTIVE;
            }
        });

        static::created(function ($user) {
            // ✅ إسناد الدور تلقائياً للمستخدمين غير السوبر آدمين
            if (!$user->is_super_admin && !empty($user->role)) {
                $user->assignRole($user->role);
            }

            // (اختياري) الاحتفاظ بسجل النشاط القديم
            ActivityLog::create([
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'user_id' => auth()->id(),
                'action' => 'user.created',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'properties' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ]
            ]);
        });
    }
}
