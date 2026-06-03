<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,     // ✅ تمت الإضافة
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'email' => $this->email,
            // 'phone' => $this->phone,            // ❌ غير موجود في جدول employees – تم حذفه
            'is_active' => $this->is_active,

            'branch' => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null,

            // Salary
            'salary' => (float) $this->salary,
            'salary_formatted' => $this->salary ? number_format($this->salary, 2) . ' EGP' : null,

            // Metadata
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'sales_count' => $this->whenLoaded('sales', function () {
                return $this->sales->count();
            }),
            'total_sales' => $this->whenLoaded('sales', function () {
                return $this->sales->sum('price');
            }),
            'sales' => SaleResource::collection($this->whenLoaded('sales')),
        ];
    }

    public function with($request): array
    {
        return [
            'status' => 'success',
        ];
    }
}
