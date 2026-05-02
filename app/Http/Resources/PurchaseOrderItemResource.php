<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,
            'purchase_order_id' => $this->purchase_order_id,
            'product_id' => $this->product_id,

            // Item Details
            'quantity' => (int) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'unit_cost_formatted' => number_format($this->unit_cost, 2) . ' EGP',

            // Computed Values
            'total' => (float) $this->total,
            'total_formatted' => number_format($this->total, 2) . ' EGP',
            'subtotal' => (float) ($this->quantity * $this->unit_cost),
            'subtotal_formatted' => number_format($this->quantity * $this->unit_cost, 2) . ' EGP',

            // Receiving Info
            'received_quantity' => (float) ($this->received_quantity ?? 0),
            'returned_quantity' => (float) ($this->returned_quantity ?? 0),
            'remaining_quantity' => (float) ($this->remaining_quantity ?? 0),
            'is_fully_received' => $this->isFullyReceived(),

            // Dates
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'title' => $this->product->title_en,
                    'title_ar' => $this->product->title_ar,
                    'stock_quantity' => $this->product->stock_quantity,
                    'image_url' => $this->product->product_image
                        ? asset('img/product/' . $this->product->product_image)
                        : null,
                ];
            }),

            'purchase_order' => $this->whenLoaded('purchaseOrder', function () {
                return [
                    'id' => $this->purchaseOrder->id,
                    'number' => $this->purchaseOrder->number,
                    'status' => $this->purchaseOrder->status,
                    'supplier_id' => $this->purchaseOrder->supplier_id,
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
