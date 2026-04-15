<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
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

            // Salary
            'salary' => (float) $this->salary,
            'salary_formatted' => $this->salary ? number_format($this->salary, 2) . ' EGP' : null,

            // Metadata
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships (when loaded)
            'sales_count' => $this->whenCounted('sales'),
            'total_sales' => $this->whenLoaded('sales', fn() => $this->sales->sum('price')),
            'sales' => SaleResource::collection($this->whenLoaded('sales')),
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
}
