<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'status'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime'];

    // ← العلاقة
    public function roles()
    {
        return $this->belongsToMany(
            \App\Models\Role::class, // نموذج Role
            'role_user',             // جدول pivot
            'user_id',               // مفتاح المستخدم
            'role_id'                // مفتاح الدور
        );
    }
}
