<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'email' => $this->email,

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
                function () {
                    return $this->getPermissions();
                }
            ),

            // Dates
            'email_verified_at' => $this->email_verified_at ? $this->email_verified_at->toIso8601String() : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                    'slug' => $this->company->slug,
                    'status' => $this->company->status,
                ];
            }),

            'roles' => RoleResource::collection($this->whenLoaded('roles')),
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
        switch ($this->role) {
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
                return $this->role ?? 'غير معروف';
        }
    }

    private function getStatusLabel(): string
    {
        return $this->isActive() ? 'نشط' : 'غير نشط';
    }

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
