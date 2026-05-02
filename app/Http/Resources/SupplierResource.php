<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,

            // Additional Fields
            'address' => $this->address,
            'contact_person' => $this->contact_person,
            'notes' => $this->notes,

            // Financial Summary
            'total_purchases' => (float) ($this->total_purchases ?? 0),
            'total_purchases_formatted' => number_format($this->total_purchases ?? 0, 2) . ' EGP',
            'total_paid' => (float) ($this->total_paid ?? 0),
            'total_paid_formatted' => number_format($this->total_paid ?? 0, 2) . ' EGP',
            'balance' => (float) ($this->balance ?? 0),
            'balance_formatted' => number_format($this->balance ?? 0, 2) . ' EGP',

            // Status
            'status' => $this->status ?? 'settled',
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),
            'has_balance' => ($this->balance ?? 0) > 0,
            'has_overpayment' => ($this->balance ?? 0) < 0,

            // Counts
            'purchases_count' => $this->whenLoaded('purchaseOrders', function () {
                return $this->purchaseOrders->count();
            }),
            'payments_count' => $this->whenLoaded('payments', function () {
                return $this->payments->count();
            }),

            // Dates
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'purchase_orders' => PurchaseOrderResource::collection($this->whenLoaded('purchaseOrders')),
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
        switch ($this->status ?? 'settled') {
            case 'owed':
                return 'مستحق عليه';
            case 'overpaid':
                return 'له رصيد';
            case 'settled':
                return 'متوازن';
            default:
                return 'غير معروف';
        }
    }

    private function getStatusColor(): string
    {
        switch ($this->status ?? 'settled') {
            case 'owed':
                return 'red';
            case 'overpaid':
                return 'orange';
            case 'settled':
                return 'green';
            default:
                return 'gray';
        }
    }
}
