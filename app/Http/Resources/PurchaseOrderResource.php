<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id'  => $this->branch_id,
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
            'items_count' => $this->whenLoaded('items', function () {
                return $this->items->count();
            }),
            'payments_count' => $this->whenLoaded('payments', function () {
                return $this->payments->count();
            }),

            // Dates
            'received_at' => $this->received_at ? $this->received_at->toIso8601String() : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier->id,
                    'name' => $this->supplier->name,
                    'email' => $this->supplier->email,
                    'phone' => $this->supplier->phone,
                ];
            }),

            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'payments' => SupplierPaymentResource::collection($this->whenLoaded('payments')),
        ];
    }

    public function with($request): array
    {
        return [
            'status' => 'success',
        ];
    }

    private function getStatusLabel(): string
    {
        switch ($this->status) {
            case 'ordered':
                return 'تم الطلب';
            case 'partially_paid':
                return 'مدفوع جزئيًا';
            case 'paid':
                return 'مدفوع';
            case 'received':
                return 'تم الاستلام';
            case 'has_return':
                return 'يوجد مرتجع';
            case 'returned':
                return 'مرتجع';
            case 'cancelled':
                return 'ملغي';
            default:
                return $this->status ?? 'غير معروف';
        }
    }

    private function getStatusColor(): string
    {
        switch ($this->status) {
            case 'ordered':
                return 'blue';
            case 'partially_paid':
                return 'orange';
            case 'paid':
                return 'green';
            case 'received':
                return 'purple';
            case 'has_return':
                return 'orange';
            case 'returned':
                return 'red';
            case 'cancelled':
                return 'red';
            default:
                return 'gray';
        }
    }
}
