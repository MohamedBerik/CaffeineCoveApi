<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
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
            'supplier_id' => $this->supplier_id,
            'number' => $this->number,

            // Amount Details
            'total' => (float) $this->total,
            'total_formatted' => number_format($this->total, 2) . ' EGP',
            'total_paid' => (float) $this->total_paid,
            'total_paid_formatted' => number_format($this->total_paid, 2) . ' EGP',
            'remaining' => (float) $this->remaining,
            'remaining_formatted' => number_format($this->remaining, 2) . ' EGP',

            // Status
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            // Flags
            'is_ordered' => $this->isOrdered(),
            'is_partially_paid' => $this->isPartiallyPaid(),
            'is_paid' => $this->isPaid(),
            'is_received' => $this->isReceived(),
            'is_cancelled' => $this->isCancelled(),
            'is_fully_paid' => $this->is_fully_paid ?? false,
            'can_be_modified' => $this->canBeModified(),
            'can_receive_items' => $this->canReceiveItems(),

            // Counts
            'items_count' => $this->whenCounted('items'),
            'payments_count' => $this->whenCounted('payments'),

            // Dates
            'received_at' => $this->received_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships (when loaded)
            'supplier' => $this->whenLoaded('supplier', fn() => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'email' => $this->supplier->email,
                'phone' => $this->supplier->phone,
            ]),

            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'payments' => SupplierPaymentResource::collection($this->whenLoaded('payments')),
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
     * Get status label in Arabic.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'ordered' => 'تم الطلب',
            'partially_paid' => 'مدفوع جزئيًا',
            'paid' => 'مدفوع',
            'received' => 'تم الاستلام',
            'has_return' => 'يوجد مرتجع',
            'returned' => 'مرتجع',
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
            'ordered' => 'blue',
            'partially_paid' => 'orange',
            'paid' => 'green',
            'received' => 'purple',
            'has_return' => 'orange',
            'returned' => 'red',
            'cancelled' => 'red',
            default => 'gray',
        };
    }
}
