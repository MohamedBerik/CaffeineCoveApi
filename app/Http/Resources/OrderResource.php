<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'email' => $this->customer->email,
                    'phone' => $this->customer->phone,
                ];
            }),

            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenLoaded('items', function () {
                return $this->items->count();
            }),
            'items_summary' => $this->whenLoaded('items', function () {
                return [
                    'total_items' => $this->items->sum('quantity'),
                    'unique_products' => $this->items->count(),
                ];
            }),

            'invoice' => $this->whenLoaded('invoice', function () {
                return [
                    'id' => $this->invoice->id,
                    'number' => $this->invoice->number,
                    'status' => $this->invoice->status,
                    'total' => $this->invoice->total,
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

    private function getStatusLabel(): string
    {
        switch ($this->status) {
            case 'pending':
                return 'قيد الانتظار';
            case 'confirmed':
                return 'مؤكد';
            case 'cancelled':
                return 'ملغي';
            default:
                return $this->status ?? 'غير معروف';
        }
    }

    private function getStatusColor(): string
    {
        switch ($this->status) {
            case 'pending':
                return 'orange';
            case 'confirmed':
                return 'green';
            case 'cancelled':
                return 'red';
            default:
                return 'gray';
        }
    }
}
