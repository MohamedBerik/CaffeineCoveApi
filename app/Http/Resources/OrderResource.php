<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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

            // Localized Fields
            'title' => $this->getTitleAttribute(),
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,

            'description' => $this->getDescriptionAttribute(),
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,

            // Order Details
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            // Financial
            'total' => (float) $this->total,
            'total_formatted' => number_format($this->total, 2) . ' EGP',

            // Flags
            'is_pending' => $this->isPending(),
            'is_confirmed' => $this->isConfirmed(),
            'is_cancelled' => $this->isCancelled(),
            'can_be_modified' => $this->canBeModified(),

            // Relations IDs
            'customer_id' => $this->customer_id,
            'created_by' => $this->created_by,

            // Dates
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships (when loaded)
            'customer' => $this->whenLoaded('customer', fn() => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ]),

            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'items_summary' => $this->whenLoaded('items', fn() => [
                'total_items' => $this->items->sum('quantity'),
                'unique_products' => $this->items->count(),
            ]),

            'invoice' => $this->whenLoaded('invoice', fn() => [
                'id' => $this->invoice->id,
                'number' => $this->invoice->number,
                'status' => $this->invoice->status,
                'total' => $this->invoice->total,
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
     * Get localized title.
     */
    private function getTitleAttribute(): string
    {
        return app()->getLocale() === 'ar'
            ? ($this->title_ar ?: $this->title_en)
            : ($this->title_en ?: $this->title_ar);
    }

    /**
     * Get localized description.
     */
    private function getDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar'
            ? ($this->description_ar ?: $this->description_en)
            : ($this->description_en ?: $this->description_ar);
    }

    /**
     * Get status label in Arabic.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'قيد الانتظار',
            'confirmed' => 'مؤكد',
            'cancelled' => 'ملغي',
            default => $this->status ?? 'غير معروف',
        };
    }

    /**
     * Get status color for UI.
     */
    private function getStatusColor(): string
    {
        return match ($this->status) {
            'pending' => 'orange',
            'confirmed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }
}
