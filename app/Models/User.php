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
    ];
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_user',
            'user_id',
            'role_id'
        );
    }

    /**
     * هل لدى المستخدم صلاحية معيّنة؟
     */
    // public function hasPermission(string $permission): bool
    // {
    //     return $this->roles()
    //         ->whereHas('permissions', function ($q) use ($permission) {
    //             $q->where('name', $permission);
    //         })
    //         ->exists();
    // }


    public function hasPermission($permission)
    {
        if ($this->role === 'admin') {
            return true; // Admin عنده كل الصلاحيات
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }
}
