<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'description' => $this->description,

            // Label
            'label' => $this->getRoleLabel(),

            // Dates
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'permissions_count' => $this->whenLoaded('permissions', function () {
                return $this->permissions->count();
            }),

            'users' => UserResource::collection($this->whenLoaded('users')),
            'users_count' => $this->whenLoaded('users', function () {
                return $this->users->count();
            }),
        ];
    }

    public function with($request): array
    {
        return [
            'status' => 'success',
        ];
    }

    private function getRoleLabel(): string
    {
        switch ($this->name) {
            case 'super_admin':
                return 'مدير النظام';
            case 'admin':
                return 'مدير';
            case 'doctor':
                return 'طبيب';
            case 'receptionist':
                return 'موظف استقبال';
            case 'user':
                return 'مستخدم';
            default:
                return $this->name ?? 'غير معروف';
        }
    }
}
