<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
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
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'roles_count' => $this->whenLoaded('roles', function () {
                return $this->roles->count();
            }),
        ];
    }

    public function with($request): array
    {
        return [
            'status' => 'success',
        ];
    }

    private function getModuleFromName(): string
    {
        if (str_contains($this->name, '.')) {
            return explode('.', $this->name)[0];
        }
        return 'general';
    }

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

    private function getModuleLabel(): string
    {
        $module = $this->module ?? $this->getModuleFromName();

        switch ($module) {
            case 'finance':
                return 'المالية';
            case 'orders':
                return 'الطلبات';
            case 'payments':
                return 'المدفوعات';
            case 'purchases':
                return 'المشتريات';
            case 'appointments':
                return 'المواعيد';
            case 'treatment_plans':
                return 'خطط العلاج';
            case 'procedures':
                return 'الإجراءات';
            case 'patients':
                return 'المرضى';
            case 'reports':
                return 'التقارير';
            case 'settings':
                return 'الإعدادات';
            case 'general':
                return 'عام';
            default:
                return $module;
        }
    }

    private function getGroup(): string
    {
        switch ($this->getModuleFromName()) {
            case 'finance':
            case 'orders':
            case 'payments':
            case 'purchases':
                return 'financial';
            case 'appointments':
            case 'treatment_plans':
            case 'procedures':
                return 'clinical';
            case 'patients':
                return 'patients';
            case 'reports':
                return 'reports';
            case 'settings':
                return 'settings';
            default:
                return 'general';
        }
    }
}
