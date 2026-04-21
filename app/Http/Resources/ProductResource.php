<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,

            // Images
            'image' => $this->product_image,
            'image_url' => $this->product_image ? asset('img/product/' . $this->product_image) : null,

            // Localized Fields
            'title' => $this->getTitleAttribute(),
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,

            'description' => $this->getDescriptionAttribute(),
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,

            // Pricing & Inventory
            'unit_price' => (float) $this->unit_price,
            'unit_price_formatted' => number_format($this->unit_price, 2) . ' EGP',
            'stock_quantity' => (int) $this->stock_quantity,
            'quantity' => (int) $this->quantity, // ✅ أضف السطر ده
            'on_hand' => (int) $this->on_hand,

            // Inventory Status
            'inventory_status' => $this->getInventoryStatus(),
            'inventory_status_label' => $this->getInventoryStatusLabel(),
            'inventory_status_color' => $this->getInventoryStatusColor(),
            'is_in_stock' => $this->isInStock(),
            'is_out_of_stock' => $this->isOutOfStock(),
            'is_low_stock' => $this->isLowStock(),

            // Computed Values
            'inventory_value' => (float) $this->inventory_value,
            'inventory_value_formatted' => number_format($this->inventory_value, 2) . ' EGP',

            // Relations IDs
            'category_id' => $this->category_id,

            // Metadata
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships (when loaded)
            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category->id,
                'title' => $this->category->title_en,
                'title_ar' => $this->category->title_ar,
            ]),

            // ✅ استبدل whenCounted بـ whenLoaded مع count()
            'order_items_count' => $this->whenLoaded('orderItems', fn() => $this->orderItems->count(), 0),
            'invoice_items_count' => $this->whenLoaded('invoiceItems', fn() => $this->invoiceItems->count(), 0),
            'stock_movements_count' => $this->whenLoaded('stockMovements', fn() => $this->stockMovements->count(), 0),

            'stock_movements' => StockMovementResource::collection($this->whenLoaded('stockMovements')),
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

    private function getInventoryStatus(): string
    {
        if ($this->stock_quantity <= 0) {
            return 'out_of_stock';
        } elseif ($this->stock_quantity <= 10) {
            return 'low_stock';
        }
        return 'in_stock';
    }

    private function getInventoryStatusLabel(): string
    {
        return match ($this->getInventoryStatus()) {
            'in_stock' => 'متوفر',
            'low_stock' => 'مخزون منخفض',
            'out_of_stock' => 'غير متوفر',
            default => 'غير معروف',
        };
    }

    private function getInventoryStatusColor(): string
    {
        return match ($this->getInventoryStatus()) {
            'in_stock' => 'green',
            'low_stock' => 'orange',
            'out_of_stock' => 'red',
            default => 'gray',
        };
    }
}
