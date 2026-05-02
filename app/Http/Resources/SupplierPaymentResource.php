<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPaymentResource extends JsonResource
{
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
            'paid_at' => $this->paid_at ? $this->paid_at->toIso8601String() : null,
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

            'purchase_order' => $this->whenLoaded('purchaseOrder', function () {
                return [
                    'id' => $this->purchaseOrder->id,
                    'number' => $this->purchaseOrder->number,
                    'total' => $this->purchaseOrder->total,
                    'status' => $this->purchaseOrder->status,
                ];
            }),

            'payer' => $this->whenLoaded('payer', function () {
                return [
                    'id' => $this->payer->id,
                    'name' => $this->payer->name,
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

    private function getMethodLabel(): string
    {
        switch ($this->method) {
            case 'cash':
                return 'نقدي';
            case 'bank_transfer':
                return 'تحويل بنكي';
            case 'check':
                return 'شيك';
            case 'other':
                return 'أخرى';
            default:
                return $this->method ?? 'غير معروف';
        }
    }
}
