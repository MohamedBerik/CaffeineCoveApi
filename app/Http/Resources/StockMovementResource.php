<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,
            'product_id' => $this->product_id,

            // Movement Details
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'type_color' => $this->getTypeColor(),
            'quantity' => (float) $this->quantity,
            'quantity_formatted' => $this->formatQuantity(),

            // Reference
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference_name' => $this->reference_name,
            'notes' => $this->notes,

            // Relations IDs
            'created_by' => $this->created_by,

            // Flags
            'is_in' => $this->isIn(),
            'is_out' => $this->isOut(),

            // Dates
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'title' => $this->product->title_en,
                    'title_ar' => $this->product->title_ar,
                    'current_stock' => $this->product->stock_quantity,
                    'image_url' => $this->product->product_image
                        ? asset('img/product/' . $this->product->product_image)
                        : null,
                ];
            }),

            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
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

    private function getTypeLabel(): string
    {
        switch ($this->type) {
            case 'in':
                return 'وارد';
            case 'out':
                return 'صادر';
            default:
                return $this->type ?? 'غير معروف';
        }
    }

    private function getTypeColor(): string
    {
        switch ($this->type) {
            case 'in':
                return 'green';
            case 'out':
                return 'red';
            default:
                return 'gray';
        }
    }

    private function formatQuantity(): string
    {
        $sign = $this->type === 'in' ? '+' : '-';
        return $sign . number_format($this->quantity, 2);
    }
}
