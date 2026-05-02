<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,

            // Localized Fields
            'title' => $this->getTitleAttribute(),
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,

            'description' => $this->getDescriptionAttribute(),
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,

            // Financial
            'price' => (float) $this->price,
            'price_formatted' => number_format($this->price, 2) . ' EGP',
            'quantity' => (int) $this->quantity,
            'total' => (float) $this->total,
            'total_formatted' => number_format($this->total, 2) . ' EGP',

            // Relations IDs
            'employee_id' => $this->employee_id,

            // Dates
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee->id,
                    'name' => $this->employee->name,
                    'email' => $this->employee->email,
                ];
            }),
        ];
    }

    public function with($request): array
    {
        return [
            'status' => 'success',
        ];
    }

    private function getTitleAttribute(): string
    {
        return app()->getLocale() === 'ar'
            ? ($this->title_ar ?: $this->title_en)
            : ($this->title_en ?: $this->title_ar);
    }

    private function getDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar'
            ? ($this->description_ar ?: $this->description_en)
            : ($this->description_en ?: $this->description_ar);
    }
}
