<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    // ⚪ مش محتاج BelongsToCompanyTrait - الصلاحيات عامة للنظام كله

    protected $fillable = [
        'name',
        'guard_name',
        'module',
        'description',
    ];

    // ============ Relationships ============

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'permission_role',
            'permission_id',
            'role_id'
        );
    }

    // ============ Scopes ============

    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    // ============ Helpers ============

    public function getModuleAttribute(): string
    {
        // استخراج اسم الـ module من اسم الصلاحية (مثلاً: users.create → users)
        if (str_contains($this->name, '.')) {
            return explode('.', $this->name)[0];
        }
        return 'general';
    }
}
