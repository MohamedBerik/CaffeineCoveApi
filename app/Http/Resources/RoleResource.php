<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
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
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships (when loaded)
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'permissions_count' => $this->whenCounted('permissions'),

            'users' => UserResource::collection($this->whenLoaded('users')),
            'users_count' => $this->whenCounted('users'),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with($request): array
    {
        return [
            'status' => 'success',
        ];
    }

    /**
     * Get role label in Arabic.
     */
    private function getRoleLabel(): string
    {
        return match ($this->name) {
            'super_admin' => 'مدير النظام',
            'admin' => 'مدير',
            'doctor' => 'طبيب',
            'receptionist' => 'موظف استقبال',
            'user' => 'مستخدم',
            default => $this->name ?? 'غير معروف',
        };
    }
}
