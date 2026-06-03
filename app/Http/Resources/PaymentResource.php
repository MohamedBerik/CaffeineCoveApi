<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id'  => $this->branch_id,
            'invoice_id' => $this->invoice_id,

            // Amount Details
            'amount' => (float) $this->amount,
            'amount_formatted' => number_format($this->amount, 2) . ' EGP',
            'applied_amount' => (float) $this->applied_amount,
            'applied_amount_formatted' => number_format($this->applied_amount, 2) . ' EGP',
            'credit_amount' => (float) $this->credit_amount,
            'credit_amount_formatted' => number_format($this->credit_amount, 2) . ' EGP',

            // Refund Info
            'total_refunded' => (float) ($this->total_refunded ?? 0),
            'total_refunded_formatted' => number_format($this->total_refunded ?? 0, 2) . ' EGP',
            'available_invoice_refund' => (float) ($this->available_invoice_refund ?? 0),
            'available_credit_refund' => (float) ($this->available_credit_refund ?? 0),
            'net_payment' => (float) ($this->net_payment ?? 0),
            'net_payment_formatted' => number_format($this->net_payment ?? 0, 2) . ' EGP',

            // Flags
            'has_credit' => $this->hasCredit(),
            'can_refund_invoice' => $this->canRefundInvoice(),
            'can_refund_credit' => $this->canRefundCredit(),
            'is_fully_refunded' => $this->isFullyRefunded(),

            // Payment Method
            'method' => $this->method,
            'method_label' => $this->getMethodLabel(),

            // Relations IDs
            'received_by' => $this->received_by,

            // Dates
            'paid_at' => $this->paid_at ? $this->paid_at->toIso8601String() : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'invoice' => $this->whenLoaded('invoice', function () {
                return [
                    'id' => $this->invoice->id,
                    'number' => $this->invoice->number,
                    'total' => $this->invoice->total,
                    'status' => $this->invoice->status,
                ];
            }),

            'receiver' => $this->whenLoaded('receiver', function () {
                return [
                    'id' => $this->receiver->id,
                    'name' => $this->receiver->name,
                ];
            }),

            'refunds' => PaymentRefundResource::collection($this->whenLoaded('refunds')),
            'refunds_count' => $this->whenLoaded('refunds', function () {
                return $this->refunds->count();
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
            case 'card':
                return 'بطاقة ائتمان';
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
