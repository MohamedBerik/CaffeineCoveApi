<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
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
            'purchases_count' => $this->whenCounted('purchaseOrders'),
            'payments_count' => $this->whenCounted('payments'),

            // Dates
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships (when loaded)
            'purchase_orders' => PurchaseOrderResource::collection($this->whenLoaded('purchaseOrders')),
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
        return match ($this->status ?? 'settled') {
            'owed' => 'مستحق عليه',
            'overpaid' => 'له رصيد',
            'settled' => 'متوازن',
            default => 'غير معروف',
        };
    }

    /**
     * Get status color for UI.
     */
    private function getStatusColor(): string
    {
        return match ($this->status ?? 'settled') {
            'owed' => 'red',
            'overpaid' => 'orange',
            'settled' => 'green',
            default => 'gray',
        };
    }
}
