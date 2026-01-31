<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

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
}
