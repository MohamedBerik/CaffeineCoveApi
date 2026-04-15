<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
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
            'module' => $this->module ?? $this->getModuleFromName(),
            'description' => $this->description,

            // Label
            'label' => $this->getPermissionLabel(),
            'module_label' => $this->getModuleLabel(),

            // Grouping
            'group' => $this->getGroup(),

            // Dates
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships (when loaded)
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'roles_count' => $this->whenCounted('roles'),
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
     * Get module name from permission name.
     */
    private function getModuleFromName(): string
    {
        if (str_contains($this->name, '.')) {
            return explode('.', $this->name)[0];
        }
        return 'general';
    }

    /**
     * Get permission label in Arabic.
     */
    private function getPermissionLabel(): string
    {
        $labels = [
            // Finance
            'finance.view' => 'عرض المالية',
            'finance.create' => 'إنشاء معاملات مالية',

            // Orders
            'orders.view' => 'عرض الطلبات',
            'orders.manage' => 'إدارة الطلبات',
            'orders.confirm' => 'تأكيد الطلبات',
            'orders.cancel' => 'إلغاء الطلبات',

            // Payments
            'payments.refund' => 'استرداد المدفوعات',

            // Purchases
            'purchases.manage' => 'إدارة المشتريات',
            'purchases.receive' => 'استلام المشتريات',
            'purchases.return' => 'مرتجع المشتريات',

            // Appointments
            'appointments.view' => 'عرض المواعيد',
            'appointments.manage' => 'إدارة المواعيد',
            'appointments.complete' => 'إكمال المواعيد',

            // Treatment Plans
            'treatment_plans.view' => 'عرض خطط العلاج',
            'treatment_plans.manage' => 'إدارة خطط العلاج',

            // Procedures
            'procedures.view' => 'عرض الإجراءات',
            'procedures.manage' => 'إدارة الإجراءات',

            // Patients
            'patients.view' => 'عرض المرضى',
            'patients.manage' => 'إدارة المرضى',

            // Reports
            'reports.view' => 'عرض التقارير',
            'reports.export' => 'تصدير التقارير',

            // Settings
            'settings.view' => 'عرض الإعدادات',
            'settings.manage' => 'إدارة الإعدادات',
        ];

        return $labels[$this->name] ?? $this->name;
    }

    /**
     * Get module label in Arabic.
     */
    private function getModuleLabel(): string
    {
        $module = $this->module ?? $this->getModuleFromName();

        return match ($module) {
            'finance' => 'المالية',
            'orders' => 'الطلبات',
            'payments' => 'المدفوعات',
            'purchases' => 'المشتريات',
            'appointments' => 'المواعيد',
            'treatment_plans' => 'خطط العلاج',
            'procedures' => 'الإجراءات',
            'patients' => 'المرضى',
            'reports' => 'التقارير',
            'settings' => 'الإعدادات',
            'general' => 'عام',
            default => $module,
        };
    }

    /**
     * Get permission group.
     */
    private function getGroup(): string
    {
        return match ($this->getModuleFromName()) {
            'finance', 'orders', 'payments', 'purchases' => 'financial',
            'appointments', 'treatment_plans', 'procedures' => 'clinical',
            'patients' => 'patients',
            'reports' => 'reports',
            'settings' => 'settings',
            default => 'general',
        };
    }
}
