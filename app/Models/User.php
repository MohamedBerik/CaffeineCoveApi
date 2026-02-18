<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Concerns\BelongsToCompanyTrait;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
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
    ];

    /* =====================================================
     | Relations
     * ===================================================== */

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_user',
            'user_id',
            'role_id'
        );
    }

    /* =====================================================
     | Helpers
     * ===================================================== */

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /*
     | صلاحيات ERP
     | لاحقًا يمكن ربطها بجدول permissions
     */
    public function hasPermission(string $permission): bool
    {
        // super admin يتجاوز كل القيود
        if ($this->isSuperAdmin()) {
            return true;
        }

        // admin داخل الشركة
        if ($this->role === 'admin') {
            return true;
        }

        // حاليا لا يوجد نظام صلاحيات دقيق بعد
        return false;
    }

    /*
     | هل المستخدم مرتبط بشركة؟
     | super admin مسموح له بدون شركة
     */
    public function mustHaveCompany(): bool
    {
        return ! $this->isSuperAdmin();
    }
}
