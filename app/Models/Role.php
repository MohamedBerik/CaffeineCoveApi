<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    // ⚪ مش محتاج BelongsToCompanyTrait - الأدوار عامة للنظام كله

    protected $fillable = [
        'name',
        'guard_name',
        'description',
    ];

    // ============ Relationships ============

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'role_user',
            'role_id',
            'user_id'
        );
    }

    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'permission_role',
            'role_id',
            'permission_id'
        );
    }

    // ============ Scopes ============

    public function scopeByName($query, string $name)
    {
        return $query->where('name', $name);
    }

    // ============ Helpers ============

    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions->contains('name', $permissionName);
    }

    public function hasAnyPermission(array $permissionNames): bool
    {
        return $this->permissions->whereIn('name', $permissionNames)->isNotEmpty();
    }

    public function hasAllPermissions(array $permissionNames): bool
    {
        return count($permissionNames) === $this->permissions->whereIn('name', $permissionNames)->count();
    }

    public function givePermissionTo(string|array|Permission $permission): void
    {
        $this->permissions()->syncWithoutDetaching(
            is_string($permission)
                ? Permission::firstOrCreate(['name' => $permission])
                : $permission
        );
    }

    public function revokePermissionTo(string|Permission $permission): void
    {
        $this->permissions()->detach($permission);
    }

    public function syncPermissions(array $permissions): void
    {
        $this->permissions()->sync($permissions);
    }

    // ============ Static Helpers ============

    public static function findByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    public static function findOrCreate(string $name): self
    {
        return static::firstOrCreate(['name' => $name]);
    }
}
