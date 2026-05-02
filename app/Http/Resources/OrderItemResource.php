<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,

            // Item Details
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'unit_price_formatted' => number_format($this->unit_price, 2) . ' EGP',

            // Computed Values
            'total' => (float) $this->total,
            'total_formatted' => number_format($this->total, 2) . ' EGP',
            'subtotal' => (float) ($this->quantity * $this->unit_price),
            'subtotal_formatted' => number_format($this->quantity * $this->unit_price, 2) . ' EGP',

            // Dates
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'title' => $this->product->title_en,
                    'title_ar' => $this->product->title_ar,
                    'unit_price' => $this->product->unit_price,
                    'stock_quantity' => $this->product->stock_quantity,
                    'image_url' => $this->product->product_image
                        ? asset('img/product/' . $this->product->product_image)
                        : null,
                ];
            }),

            'order' => $this->whenLoaded('order', function () {
                return [
                    'id' => $this->order->id,
                    'title' => $this->order->title_en,
                    'status' => $this->order->status,
                    'customer_id' => $this->order->customer_id,
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
}
