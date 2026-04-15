<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPaymentResource extends JsonResource
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
            'purchase_order_id' => $this->purchase_order_id,

            // Amount
            'amount' => (float) $this->amount,
            'amount_formatted' => number_format($this->amount, 2) . ' EGP',

            // Payment Method
            'method' => $this->method,
            'method_label' => $this->getMethodLabel(),

            // Relations IDs
            'paid_by' => $this->paid_by,

            // Dates
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships (when loaded)
            'supplier' => $this->whenLoaded('supplier', fn() => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'email' => $this->supplier->email,
                'phone' => $this->supplier->phone,
            ]),

            'purchase_order' => $this->whenLoaded('purchaseOrder', fn() => [
                'id' => $this->purchaseOrder->id,
                'number' => $this->purchaseOrder->number,
                'total' => $this->purchaseOrder->total,
                'status' => $this->purchaseOrder->status,
            ]),

            'payer' => $this->whenLoaded('payer', fn() => [
                'id' => $this->payer->id,
                'name' => $this->payer->name,
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
     * Get payment method label in Arabic.
     */
    private function getMethodLabel(): string
    {
        return match ($this->method) {
            'cash' => 'نقدي',
            'bank_transfer' => 'تحويل بنكي',
            'check' => 'شيك',
            'other' => 'أخرى',
            default => $this->method ?? 'غير معروف',
        };
    }
}
