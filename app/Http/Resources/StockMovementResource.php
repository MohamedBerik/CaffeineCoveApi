<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
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
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships (when loaded)
            'product' => $this->whenLoaded('product', fn() => [
                'id' => $this->product->id,
                'title' => $this->product->title_en,
                'title_ar' => $this->product->title_ar,
                'current_stock' => $this->product->stock_quantity,
                'image_url' => $this->product->product_image
                    ? asset('img/product/' . $this->product->product_image)
                    : null,
            ]),

            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
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
     * Get type label in Arabic.
     */
    private function getTypeLabel(): string
    {
        return match ($this->type) {
            'in' => 'وارد',
            'out' => 'صادر',
            default => $this->type ?? 'غير معروف',
        };
    }

    /**
     * Get type color for UI.
     */
    private function getTypeColor(): string
    {
        return match ($this->type) {
            'in' => 'green',
            'out' => 'red',
            default => 'gray',
        };
    }

    /**
     * Format quantity with sign.
     */
    private function formatQuantity(): string
    {
        $sign = $this->type === 'in' ? '+' : '-';
        return $sign . number_format($this->quantity, 2);
    }
}
