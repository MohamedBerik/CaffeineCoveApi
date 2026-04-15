<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'company_id' => $this->company_id,
            'name' => $this->name,
            'email' => $this->email,

            // ❌ متخليش الباسورد يظهر في الـ Response أبدًا
            // 'password' => $this->password,

            // Role & Status
            'role' => $this->role,
            'role_label' => $this->getRoleLabel(),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'is_active' => $this->isActive(),

            // Super Admin Flag
            'is_super_admin' => (bool) $this->is_super_admin,

            // Permissions (if needed)
            'permissions' => $this->when(
                $this->relationLoaded('roles'),
                fn() =>
                $this->getPermissions()
            ),

            // Dates
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships (when loaded)
            'company' => $this->whenLoaded('company', fn() => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'slug' => $this->company->slug,
                'status' => $this->company->status,
            ]),

            'roles' => RoleResource::collection($this->whenLoaded('roles')),
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
        return match ($this->role) {
            'super_admin' => 'مدير النظام',
            'admin' => 'مدير',
            'doctor' => 'طبيب',
            'receptionist' => 'موظف استقبال',
            'user' => 'مستخدم',
            default => $this->role ?? 'غير معروف',
        };
    }

    /**
     * Get status label in Arabic.
     */
    private function getStatusLabel(): string
    {
        return $this->isActive() ? 'نشط' : 'غير نشط';
    }

    /**
     * Get user permissions.
     */
    private function getPermissions(): array
    {
        if ($this->is_super_admin) {
            return ['*'];
        }

        if ($this->role === 'admin') {
            return [
                'finance.view',
                'finance.create',
                'orders.view',
                'orders.manage',
                'appointments.view',
                'appointments.manage',
                'patients.view',
                'patients.manage',
                'treatment_plans.view',
                'treatment_plans.manage',
                'procedures.view',
                'procedures.manage',
                'reports.view',
            ];
        }

        return [
            'appointments.view',
            'patients.view',
        ];
    }
}
